<?php

namespace Twdnhfr\BesRag\Decomposition;

use Twdnhfr\BesRag\Contracts\GoalDecomposer;
use Twdnhfr\BesRag\Data\GoalNode;
use Twdnhfr\BesRag\Data\GoalTree;

/**
 * Returns a predefined goal tree — for tests and for callers that already
 * know the evidence requirements of their domain.
 */
class StaticGoalDecomposer implements GoalDecomposer
{
    public function __construct(protected GoalTree $tree) {}

    /**
     * @param  list<array<string, mixed>>  $goals
     */
    public static function fromArray(array $goals): self
    {
        return new self(GoalTree::fromArray($goals));
    }

    public function decompose(string $question, ?GoalNode $target = null): GoalTree
    {
        if ($target === null) {
            return $this->tree;
        }

        // A static decomposer cannot refine goals further.
        return new GoalTree;
    }
}
