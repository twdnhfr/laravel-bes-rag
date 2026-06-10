<?php

use Twdnhfr\BesRag\Contracts\Verifier;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\GoalNode;
use Twdnhfr\BesRag\Data\GoalTree;
use Twdnhfr\BesRag\Data\VerificationScore;
use Twdnhfr\BesRag\Scoring\RecursiveGoalScorer;
use Twdnhfr\BesRag\Scoring\VerifierRegistry;

function fixedVerifier(array $scores): Verifier
{
    return new class($scores) implements Verifier
    {
        public function __construct(public array $scores) {}

        public function verify(GoalNode $goal, EvidenceTrail $trail): VerificationScore
        {
            return new VerificationScore($this->scores[$goal->id] ?? 0.0);
        }
    };
}

it('returns the verifier score for leaves', function () {
    $registry = new VerifierRegistry;
    $registry->register('fixed', fixedVerifier(['g1' => 0.5]));

    $tree = new GoalTree([new GoalNode('g1', 'leaf goal', verifierType: 'fixed')]);
    $trail = new EvidenceTrail;

    $scorer = new RecursiveGoalScorer($registry, alpha: 0.3);

    expect($scorer->score($tree, $trail))->toEqualWithDelta(0.5, 1e-9)
        ->and($trail->goalScores['g1'])->toEqualWithDelta(0.5, 1e-9);
});

it('blends self and child scores with alpha', function () {
    $registry = new VerifierRegistry;
    $registry->register('fixed', fixedVerifier(['root' => 0.2, 'c1' => 0.4, 'c2' => 0.8]));

    $root = new GoalNode('root', 'root goal', verifierType: 'fixed');
    $root->addChild(new GoalNode('c1', 'child one', verifierType: 'fixed'));
    $root->addChild(new GoalNode('c2', 'child two', verifierType: 'fixed'));

    $tree = new GoalTree([$root]);
    $trail = new EvidenceTrail;

    $scorer = new RecursiveGoalScorer($registry, alpha: 0.3);

    // 0.3 * 0.2 + 0.7 * mean(0.4, 0.8) = 0.06 + 0.42 = 0.48
    expect($scorer->score($tree, $trail))->toEqualWithDelta(0.48, 1e-9);
});

it('short-circuits satisfied nodes to 1.0 regardless of children', function () {
    $registry = new VerifierRegistry;
    $registry->register('fixed', fixedVerifier(['root' => 1.0, 'c1' => 0.0]));

    $root = new GoalNode('root', 'root goal', verifierType: 'fixed');
    $root->addChild(new GoalNode('c1', 'unsatisfied child', verifierType: 'fixed'));

    $tree = new GoalTree([$root]);

    $scorer = new RecursiveGoalScorer($registry, alpha: 0.3);

    expect($scorer->score($tree, new EvidenceTrail))->toEqualWithDelta(1.0, 1e-9);
});

it('scores unknown verifier types as zero instead of failing', function () {
    $registry = new VerifierRegistry;

    $tree = new GoalTree([new GoalNode('g1', 'goal', verifierType: 'does_not_exist')]);
    $trail = new EvidenceTrail;

    $scorer = new RecursiveGoalScorer($registry, alpha: 0.3);

    expect($scorer->score($tree, $trail))->toEqualWithDelta(0.0, 1e-9)
        ->and($trail->goalScoreReasons['g1'])->toHaveKey('error');
});
