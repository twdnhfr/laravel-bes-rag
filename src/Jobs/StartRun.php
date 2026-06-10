<?php

namespace Twdnhfr\BesRag\Jobs;

use Twdnhfr\BesRag\Engine\Engine;
use Twdnhfr\BesRag\Models\Run;

/**
 * Stage 1: backward decomposition of the question into the goal tree.
 */
class StartRun extends RunJob
{
    protected function process(Engine $engine, Run $run): void
    {
        $engine->decompose($run);

        $this->next(new SeedCandidates($run->id));
    }
}
