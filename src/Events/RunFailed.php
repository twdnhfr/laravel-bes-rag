<?php

namespace Twdnhfr\BesRag\Events;

use Twdnhfr\BesRag\Models\Run;

class RunFailed
{
    public function __construct(public Run $run, public string $reason) {}
}
