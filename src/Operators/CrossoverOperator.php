<?php

namespace Twdnhfr\BesRag\Operators;

use Twdnhfr\BesRag\Contracts\EvolutionOperator;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\Operation;
use Twdnhfr\BesRag\Data\StepType;
use Twdnhfr\BesRag\Data\TrailStep;
use Twdnhfr\BesRag\Engine\SearchRun;

/**
 * Cut along goal boundaries (never blindly by step index): the child takes
 * A's phases for the first half of A's touched goals, then B's phases for
 * everything B covers beyond that prefix.
 */
class CrossoverOperator implements EvolutionOperator
{
    public function name(): string
    {
        return 'crossover';
    }

    public function arity(): int
    {
        return 2;
    }

    public function apply(SearchRun $run, EvidenceTrail ...$parents): ?EvidenceTrail
    {
        [$a, $b] = $parents;

        $aGoals = $a->touchedGoalIds();
        $bGoals = $b->touchedGoalIds();

        if ($aGoals === [] || $bGoals === []) {
            return null;
        }

        $prefixGoals = array_slice($aGoals, 0, max(1, (int) ceil(count($aGoals) / 2)));
        $suffixGoals = array_values(array_diff($bGoals, $prefixGoals));

        if ($suffixGoals === []) {
            return null; // B adds no phase beyond A's prefix — crossover degenerates.
        }

        $child = new EvidenceTrail;
        $child->operation = Operation::Crossover;
        $child->parentIds = array_values(array_filter([$a->id, $b->id], fn ($id) => $id !== null));

        foreach ($prefixGoals as $goalId) {
            foreach ($a->stepsForGoal($goalId) as $step) {
                if ($step->type !== StepType::Answer) {
                    $child->addStep(new TrailStep($step->type, $step->content, $step->goalId));
                }
            }
        }

        foreach ($suffixGoals as $goalId) {
            foreach ($b->stepsForGoal($goalId) as $step) {
                if ($step->type !== StepType::Answer) {
                    $child->addStep(new TrailStep($step->type, $step->content, $step->goalId));
                }
            }
        }

        if ($child->steps === []) {
            return null;
        }

        return $child;
    }
}
