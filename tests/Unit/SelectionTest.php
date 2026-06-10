<?php

use Twdnhfr\BesRag\Contracts\EvolutionOperator;
use Twdnhfr\BesRag\Data\BesConfig;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\GoalTree;
use Twdnhfr\BesRag\Engine\BoltzmannSelection;
use Twdnhfr\BesRag\Engine\OperatorMix;
use Twdnhfr\BesRag\Engine\SearchRun;
use Twdnhfr\BesRag\Models\Run;

function candidate(float $score, array $goalScores = []): EvidenceTrail
{
    $trail = new EvidenceTrail;
    $trail->effectiveScore = $score;
    $trail->goalScores = $goalScores;

    return $trail;
}

function poolRun(array $candidates, int $usedBudget = 0, int $budget = 30): SearchRun
{
    $run = new Run;
    $run->used_budget = $usedBudget;

    $config = BesConfig::fromArray(['budget' => $budget]);

    return new SearchRun($run, $config, new GoalTree, $candidates);
}

it('anneals the temperature across the budget', function () {
    $early = poolRun([], usedBudget: 0);
    $late = poolRun([], usedBudget: 30);

    expect($early->temperature())->toEqualWithDelta(1.5, 1e-9)
        ->and($late->temperature())->toEqualWithDelta(0.3, 1e-9);
});

it('prefers high-scoring parents when the random draw is low', function () {
    $weak = candidate(0.1);
    $strong = candidate(0.9);

    // random() = 0 always picks the first index whose cumulative weight
    // crosses 0 — softmax order is preserved, so sample the distribution
    // many times deterministically and count.
    $sequence = [0.05, 0.25, 0.45, 0.65, 0.85];
    $index = 0;
    $policy = new BoltzmannSelection(function () use (&$index, $sequence) {
        return $sequence[$index++ % count($sequence)];
    });

    $picks = ['weak' => 0, 'strong' => 0];

    for ($i = 0; $i < 5; $i++) {
        $picked = $policy->selectParent(poolRun([$weak, $strong], usedBudget: 30));
        $picks[$picked === $strong ? 'strong' : 'weak']++;
    }

    expect($picks['strong'])->toBeGreaterThan($picks['weak']);
});

it('selects a complementary second parent', function () {
    $first = candidate(0.9, ['g1' => 1.0, 'g2' => 0.0]);
    $overlapping = candidate(0.8, ['g1' => 1.0, 'g2' => 0.0]);
    $complementary = candidate(0.3, ['g1' => 0.0, 'g2' => 1.0]);

    // A mid-range draw at low temperature behaves near-argmax: the first
    // parent is the strongest candidate, the second the most complementary.
    $policy = new BoltzmannSelection(fn () => 0.5);

    [$a, $b] = $policy->selectPair(poolRun([$first, $overlapping, $complementary], usedBudget: 30));

    expect($a)->toBe($first)
        ->and($b)->toBe($complementary);
});

function namedOperator(string $name, int $arity): EvolutionOperator
{
    return new class($name, $arity) implements EvolutionOperator
    {
        public function __construct(public string $opName, public int $opArity) {}

        public function name(): string
        {
            return $this->opName;
        }

        public function arity(): int
        {
            return $this->opArity;
        }

        public function apply(SearchRun $run, EvidenceTrail ...$parents): ?EvidenceTrail
        {
            return null;
        }
    };
}

it('excludes pair operators when only one candidate exists', function () {
    $mix = new OperatorMix(
        [namedOperator('expand', 1), namedOperator('combine', 2)],
        ['expand' => 0.5, 'combine' => 0.5],
        fn () => 0.99,
    );

    $sampled = $mix->sample(poolRun([candidate(0.5)]));

    expect($sampled->name())->toBe('expand');
});

it('samples operators according to the configured mix', function () {
    $mix = new OperatorMix(
        [namedOperator('expand', 1), namedOperator('delete', 1)],
        ['expand' => 0.7, 'delete' => 0.3],
        fn () => 0.8, // cumulative: expand 0.7 < 0.8 <= 1.0 → delete
    );

    expect($mix->sample(poolRun([candidate(0.5)]))->name())->toBe('delete');
});

it('ignores operators with zero weight', function () {
    $mix = new OperatorMix(
        [namedOperator('expand', 1), namedOperator('delete', 1)],
        ['expand' => 1.0, 'delete' => 0.0],
        fn () => 0.999,
    );

    expect($mix->sample(poolRun([candidate(0.5)]))->name())->toBe('expand');
});
