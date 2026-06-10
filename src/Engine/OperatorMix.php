<?php

namespace Twdnhfr\BesRag\Engine;

use Closure;
use RuntimeException;
use Twdnhfr\BesRag\Contracts\EvolutionOperator;

/**
 * Weighted operator sampling per the configured mix. Operators whose
 * arity cannot be satisfied (pair operators with fewer than two
 * candidates) are excluded from the draw.
 */
class OperatorMix
{
    protected Closure $random;

    /**
     * @param  list<EvolutionOperator>  $operators
     * @param  array<string, float>  $mix
     * @param  (Closure(): float)|null  $random
     */
    public function __construct(
        protected array $operators,
        protected array $mix,
        ?Closure $random = null,
    ) {
        $this->random = $random ?? fn (): float => mt_rand() / mt_getrandmax();
    }

    public function sample(SearchRun $run): EvolutionOperator
    {
        $eligible = [];
        $weights = [];

        foreach ($this->operators as $operator) {
            $weight = (float) ($this->mix[$operator->name()] ?? 0.0);

            if ($weight <= 0.0) {
                continue;
            }

            if ($operator->arity() > count($run->candidates)) {
                continue;
            }

            $eligible[] = $operator;
            $weights[] = $weight;
        }

        if ($eligible === []) {
            throw new RuntimeException('No eligible evolution operator for the current candidate pool.');
        }

        $threshold = ($this->random)() * array_sum($weights);
        $cumulative = 0.0;

        foreach ($eligible as $index => $operator) {
            $cumulative += $weights[$index];

            if ($cumulative >= $threshold) {
                return $operator;
            }
        }

        return $eligible[count($eligible) - 1];
    }

    /**
     * @return list<EvolutionOperator>
     */
    public function operators(): array
    {
        return $this->operators;
    }
}
