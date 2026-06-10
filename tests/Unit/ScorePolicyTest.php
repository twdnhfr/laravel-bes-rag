<?php

use Twdnhfr\BesRag\Scoring\BucketInterpolatedScorePolicy;
use Twdnhfr\BesRag\Scoring\WeightedScorePolicy;

it('lets the raw score dominate across buckets', function () {
    $policy = new BucketInterpolatedScorePolicy(bucketSize: 0.1);

    $wellGrounded = $policy->effectiveScore(0.8, 0.1);
    $topicallyNice = $policy->effectiveScore(0.5, 1.0);

    // A trail that merely "looks right" to the goal tree must never beat
    // a meaningfully better-grounded trail.
    expect($wellGrounded)->toBeGreaterThan($topicallyNice);
});

it('breaks ties inside a bucket with the backward score', function () {
    $policy = new BucketInterpolatedScorePolicy(bucketSize: 0.1);

    $a = $policy->effectiveScore(0.82, 0.9);
    $b = $policy->effectiveScore(0.84, 0.1);

    // Same bucket (0.8): higher backward score wins.
    expect($a)->toBeGreaterThan($b);
});

it('blends linearly in the weighted policy', function () {
    $policy = new WeightedScorePolicy(0.6, 0.4);

    expect($policy->effectiveScore(0.5, 1.0))->toEqualWithDelta(0.7, 1e-9);
});
