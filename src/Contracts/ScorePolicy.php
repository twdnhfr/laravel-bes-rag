<?php

namespace Twdnhfr\BesRag\Contracts;

interface ScorePolicy
{
    /**
     * Combine the hard raw score (groundedness, citations, ...) with the
     * dense backward goal-tree score into one effective ranking score.
     */
    public function effectiveScore(float $rawScore, float $backwardScore): float;
}
