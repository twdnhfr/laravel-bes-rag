<?php

namespace Twdnhfr\BesRag\Events;

use Twdnhfr\BesRag\Models\Run;

class RunStarted
{
    public function __construct(public Run $run) {}
}
