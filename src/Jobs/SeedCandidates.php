<?php

namespace Twdnhfr\BesRag\Jobs;

use Twdnhfr\BesRag\Engine\Engine;
use Twdnhfr\BesRag\Models\Run;

/**
 * Stage 2: seed the initial evidence trails and score them.
 */
class SeedCandidates extends RunJob
{
    protected function process(Engine $engine, Run $run): void
    {
        $engine->seed($run);

        $this->next(new SearchStep($run->id));
    }
}
