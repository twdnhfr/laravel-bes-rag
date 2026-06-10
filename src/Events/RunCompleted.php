<?php

namespace Twdnhfr\BesRag\Events;

use Twdnhfr\BesRag\Models\Run;

class RunCompleted
{
    public function __construct(public Run $run) {}
}
