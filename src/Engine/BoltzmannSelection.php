<?php

namespace Twdnhfr\BesRag\Engine;

use Closure;
use RuntimeException;
use Twdnhfr\BesRag\Contracts\SearchPolicy;
use Twdnhfr\BesRag\Data\EvidenceTrail;

/**
 * Boltzmann (softmax) parent selection over effective scores, with the
 * temperature annealed across the run's budget: explorative early, greedy
 * late. Pair selection picks the second parent by complementarity — how
 * much it covers goals the first parent does not (the BES pair score).
 */
class BoltzmannSelection implements SearchPolicy
{
    protected Closure $random;

    /**
     * @param  (Closure(): float)|null  $random  returns [0, 1); injectable for tests
     */
    public function __construct(?Closure $random = null)
    {
        $this->random = $random ?? fn (): float => mt_rand() / mt_getrandmax();
    }

    public function selectParent(SearchRun $run): EvidenceTrail
    {
        if ($run->candidates === []) {
            throw new RuntimeException('Cannot select a parent from an empty candidate pool.');
        }

        $weights = array_map(
            fn (EvidenceTrail $trail) => $trail->effectiveScore,
            $run->candidates,
        );

        return $run->candidates[$this->sample($weights, $run->temperature())];
    }

    public function selectPair(SearchRun $run): array
    {
        if (count($run->candidates) < 2) {
            throw new RuntimeException('Pair selection needs at least two candidates.');
        }

        $first = $this->selectParent($run);

        $others = array_values(array_filter(
            $run->candidates,
            fn (EvidenceTrail $trail) => $trail !== $first,
        ));

        $weights = array_map(
            fn (EvidenceTrail $trail) => $this->pairScore($first, $trail),
            $others,
        );

        $second = $others[$this->sample($weights, $run->temperature())];

        return [$first, $second];
    }

    /**
     * Complementarity: total goal coverage the candidate adds on top of
     * the first parent, plus a small base weight so pure-overlap parents
     * remain selectable.
     */
    protected function pairScore(EvidenceTrail $first, EvidenceTrail $candidate): float
    {
        $gain = 0.0;

        foreach ($candidate->goalScores as $goalId => $score) {
            $gain += max(0.0, $score - ($first->goalScores[$goalId] ?? 0.0));
        }

        return $gain + 0.1 * $candidate->effectiveScore + 0.01;
    }

    /**
     * @param  list<float>  $weights
     */
    protected function sample(array $weights, float $temperature): int
    {
        $temperature = max(0.01, $temperature);

        $max = max($weights);
        $exponentials = array_map(
            fn (float $weight) => exp(($weight - $max) / $temperature),
            $weights,
        );

        $total = array_sum($exponentials);
        $threshold = ($this->random)() * $total;

        $cumulative = 0.0;

        foreach ($exponentials as $index => $value) {
            $cumulative += $value;

            if ($cumulative >= $threshold) {
                return $index;
            }
        }

        return count($weights) - 1;
    }
}
