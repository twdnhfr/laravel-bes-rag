<?php

namespace Twdnhfr\BesRag\Jobs;

use Twdnhfr\BesRag\Engine\Engine;
use Twdnhfr\BesRag\Models\Run;

/**
 * Stage 4: pick the best grounded terminal trail and synthesize the final
 * answer strictly from its selected evidence.
 */
class FinalizeAnswer extends RunJob
{
    protected function process(Engine $engine, Run $run): void
    {
        $engine->finalize($run);
    }
}
