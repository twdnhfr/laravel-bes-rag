<?php

namespace Twdnhfr\BesRag\Data;

final class VerificationScore
{
    /**
     * @param  float  $score  0.0 .. 1.0
     * @param  array<string, mixed>  $reason
     */
    public function __construct(
        public float $score,
        public array $reason = [],
    ) {
        $this->score = max(0.0, min(1.0, $score));
    }

    public function satisfied(float $threshold = 0.999): bool
    {
        return $this->score >= $threshold;
    }
}
