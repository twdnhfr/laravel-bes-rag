<?php

namespace Twdnhfr\BesRag\Operators;

use Twdnhfr\BesRag\Contracts\EvolutionOperator;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\Operation;
use Twdnhfr\BesRag\Data\StepType;
use Twdnhfr\BesRag\Data\TrailStep;
use Twdnhfr\BesRag\Engine\SearchRun;

/**
 * Remove the retrieval/evidence phase that contributes least: all steps of
 * the goal with the lowest verifier score. Useful when an early bad query
 * polluted the trail with irrelevant or contradictory evidence.
 */
class DeleteOperator implements EvolutionOperator
{
    public function name(): string
    {
        return 'delete';
    }

    public function arity(): int
    {
        return 1;
    }

    public function apply(SearchRun $run, EvidenceTrail ...$parents): ?EvidenceTrail
    {
        $parent = $parents[0];
        $touched = $parent->touchedGoalIds();

        if (count($touched) < 2) {
            return null; // Deleting the only phase would leave an empty trail.
        }

        // Lowest-scoring touched goal is the deletion target.
        $target = null;
        $lowest = PHP_FLOAT_MAX;

        foreach ($touched as $goalId) {
            $score = $parent->goalScores[$goalId] ?? 0.0;

            if ($score < $lowest) {
                $lowest = $score;
                $target = $goalId;
            }
        }

        if ($target === null || $lowest >= 0.999) {
            return null; // Nothing weak to remove.
        }

        $child = $parent->childCopy(Operation::Delete);

        $child->steps = array_values(array_filter(
            $child->steps,
            fn (TrailStep $step) => $step->goalId !== $target && $step->type !== StepType::Answer,
        ));

        if ($child->steps === []) {
            return null;
        }

        $child->answerDraft = null;
        $child->terminal = false;

        return $child;
    }
}
