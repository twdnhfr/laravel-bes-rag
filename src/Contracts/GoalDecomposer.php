<?php

namespace Twdnhfr\BesRag\Contracts;

use Twdnhfr\BesRag\Data\GoalNode;
use Twdnhfr\BesRag\Data\GoalTree;

interface GoalDecomposer
{
    /**
     * Decompose a question into checkable sub-goals.
     *
     * When `$target` is given, decompose that unsatisfied goal further
     * (backward search expansion) instead of the root question.
     */
    public function decompose(string $question, ?GoalNode $target = null): GoalTree;
}
