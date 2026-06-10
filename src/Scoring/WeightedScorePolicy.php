<?php

namespace Twdnhfr\BesRag\Scoring;

use Twdnhfr\BesRag\Contracts\ScorePolicy;

/**
 * Simple MVP blend: effective = w_raw * raw + w_backward * backward.
 */
class WeightedScorePolicy implements ScorePolicy
{
    public function __construct(
        protected float $rawWeight = 0.6,
        protected float $backwardWeight = 0.4,
    ) {}

    public function effectiveScore(float $rawScore, float $backwardScore): float
    {
        return $this->rawWeight * $rawScore + $this->backwardWeight * $backwardScore;
    }
}
