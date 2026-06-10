<?php

namespace Twdnhfr\BesRag\Contracts;

use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Engine\SearchRun;

interface CandidateGenerator
{
    /**
     * Create a fresh seed trail (no parent) using a distinct query strategy.
     */
    public function seed(SearchRun $run, int $seedIndex): EvidenceTrail;

    /**
     * Expand a parent trail towards its next open sub-goal.
     */
    public function expand(SearchRun $run, EvidenceTrail $parent): EvidenceTrail;
}
