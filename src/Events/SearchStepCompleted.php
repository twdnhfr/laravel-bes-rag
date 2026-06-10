<?php

namespace Twdnhfr\BesRag\Events;

use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Models\Run;

class SearchStepCompleted
{
    public function __construct(
        public Run $run,
        public string $operator,
        public ?EvidenceTrail $child,
    ) {}
}
