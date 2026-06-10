<?php

namespace Twdnhfr\BesRag\Operators;

use Twdnhfr\BesRag\Contracts\EvolutionOperator;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\Operation;
use Twdnhfr\BesRag\Data\StepType;
use Twdnhfr\BesRag\Data\TrailStep;
use Twdnhfr\BesRag\Engine\SearchRun;

/**
 * Replace trail A's evidence for one sub-goal with trail B's better
 * evidence for the same sub-goal.
 */
class TranslocateOperator implements EvolutionOperator
{
    public function name(): string
    {
        return 'translocate';
    }

    public function arity(): int
    {
        return 2;
    }

    public function apply(SearchRun $run, EvidenceTrail ...$parents): ?EvidenceTrail
    {
        [$a, $b] = $parents;

        // Find the goal where B most outperforms A and both have steps.
        $target = null;
        $bestGain = 0.0;

        foreach ($a->touchedGoalIds() as $goalId) {
            if ($b->stepsForGoal($goalId) === []) {
                continue;
            }

            $gain = ($b->goalScores[$goalId] ?? 0.0) - ($a->goalScores[$goalId] ?? 0.0);

            if ($gain > $bestGain) {
                $bestGain = $gain;
                $target = $goalId;
            }
        }

        if ($target === null) {
            return null;
        }

        $child = $a->childCopy(Operation::Translocate);
        $child->parentIds = array_values(array_filter([$a->id, $b->id], fn ($id) => $id !== null));

        // Drop A's phase for the target goal, splice in B's.
        $child->steps = array_values(array_filter(
            $child->steps,
            fn (TrailStep $step) => $step->goalId !== $target && $step->type !== StepType::Answer,
        ));

        foreach ($b->stepsForGoal($target) as $step) {
            if ($step->type === StepType::Answer) {
                continue;
            }

            $child->addStep(new TrailStep($step->type, $step->content, $step->goalId));
        }

        $child->answerDraft = null;
        $child->terminal = false;

        return $child;
    }
}
