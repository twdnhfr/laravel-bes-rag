<?php

namespace Twdnhfr\BesRag\Scoring;

use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\GoalNode;
use Twdnhfr\BesRag\Data\GoalTree;
use Twdnhfr\BesRag\Data\VerificationScore;

/**
 * Dense backward score over the goal tree, mirroring BES goal_tree.py:
 *
 *   score(goal) = 1.0                       if verifier(goal) ~ satisfied
 *               = verifier(goal)            if leaf
 *               = alpha * verifier(goal)
 *                 + (1 - alpha) * mean(score(children))
 *
 * alpha ~ 0.3 for generic trees, ~ 0.7 for strictly sequential multi-hop
 * chains where the node's own coverage matters more than subdivision.
 */
class RecursiveGoalScorer
{
    public function __construct(
        protected VerifierRegistry $verifiers,
        protected float $alpha = 0.3,
        protected float $satisfiedEpsilon = 1e-6,
    ) {}

    /**
     * Scores every node and writes per-goal scores + reasons onto the
     * trail. Returns the backward score (mean over root nodes).
     */
    public function score(GoalTree $tree, EvidenceTrail $trail): float
    {
        // Two passes: dependency-gated verifiers read other goals' scores,
        // so all plain verifier scores must exist before combination.
        foreach ($tree->allNodes() as $node) {
            $result = $this->verify($node, $trail);
            $trail->goalScores[$node->id] = $result->score;
            $trail->goalScoreReasons[$node->id] = $result->reason;
        }

        $rootScores = array_map(
            fn (GoalNode $root) => $this->combined($root, $trail),
            $tree->roots,
        );

        foreach ($tree->roots as $root) {
            // Persist the combined (not just self) score for inner nodes.
            $this->writeCombined($root, $trail);
        }

        return $rootScores === [] ? 0.0 : array_sum($rootScores) / count($rootScores);
    }

    protected function verify(GoalNode $node, EvidenceTrail $trail): VerificationScore
    {
        if (! $this->verifiers->has($node->verifierType)) {
            return new VerificationScore(0.0, ['error' => "unknown verifier [{$node->verifierType}]"]);
        }

        return $this->verifiers->get($node->verifierType)->verify($node, $trail);
    }

    protected function combined(GoalNode $node, EvidenceTrail $trail): float
    {
        $self = $trail->goalScores[$node->id] ?? 0.0;

        if ($self >= 1.0 - $this->satisfiedEpsilon) {
            return 1.0;
        }

        if ($node->isLeaf()) {
            return $self;
        }

        $childScores = array_map(
            fn (GoalNode $child) => $this->combined($child, $trail),
            $node->children,
        );

        $childMean = array_sum($childScores) / count($childScores);

        return $this->alpha * $self + (1 - $this->alpha) * $childMean;
    }

    protected function writeCombined(GoalNode $node, EvidenceTrail $trail): void
    {
        $trail->goalScores[$node->id] = $this->combined($node, $trail);

        foreach ($node->children as $child) {
            $this->writeCombined($child, $trail);
        }
    }
}
