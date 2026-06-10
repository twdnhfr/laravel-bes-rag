<?php

namespace Twdnhfr\BesRag\Scoring;

use Twdnhfr\BesRag\Contracts\ScorePolicy;

/**
 * The hard raw score (groundedness, citations) dominates: candidates are
 * ranked by their raw-score bucket, and the dense backward score only
 * breaks ties *within* a bucket. A trail can never outrank a meaningfully
 * better-grounded trail just because it "looks topically right" to the
 * goal tree — the recommended policy for production.
 */
class BucketInterpolatedScorePolicy implements ScorePolicy
{
    public function __construct(protected float $bucketSize = 0.1) {}

    public function effectiveScore(float $rawScore, float $backwardScore): float
    {
        $bucketSize = max(0.001, min(1.0, $this->bucketSize));

        $bucket = floor(max(0.0, min(1.0, $rawScore)) / $bucketSize) * $bucketSize;

        // Backward score interpolates strictly inside the bucket.
        return $bucket + max(0.0, min(1.0, $backwardScore)) * $bucketSize * 0.999;
    }
}
