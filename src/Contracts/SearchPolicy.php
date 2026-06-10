<?php

namespace Twdnhfr\BesRag\Contracts;

use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Engine\SearchRun;

interface SearchPolicy
{
    public function selectParent(SearchRun $run): EvidenceTrail;

    /**
     * Select two distinct, ideally complementary parents.
     *
     * @return array{EvidenceTrail, EvidenceTrail}
     */
    public function selectPair(SearchRun $run): array;
}
