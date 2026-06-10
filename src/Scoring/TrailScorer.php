<?php

namespace Twdnhfr\BesRag\Scoring;

use Twdnhfr\BesRag\Contracts\ScorePolicy;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Engine\SearchRun;

/**
 * Scores one trail end-to-end: backward (recursive goal tree), raw
 * (groundedness & citations) and the combined effective score.
 */
class TrailScorer
{
    public function __construct(
        protected RecursiveGoalScorer $goalScorer,
        protected RawScoreCalculator $rawScorer,
        protected ScorePolicy $policy,
    ) {}

    public function score(SearchRun $run, EvidenceTrail $trail): void
    {
        $trail->backwardScore = $this->goalScorer->score($run->goalTree, $trail);
        $trail->rawScore = $this->rawScorer->score($run->question(), $trail, $run->config);
        $trail->effectiveScore = $this->policy->effectiveScore($trail->rawScore, $trail->backwardScore);
    }
}
