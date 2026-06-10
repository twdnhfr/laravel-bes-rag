<?php

namespace Twdnhfr\BesRag\Jobs;

use Twdnhfr\BesRag\Engine\Engine;
use Twdnhfr\BesRag\Models\Run;

/**
 * Stage 3 (self-redispatching): one evolution step per job — sample an
 * operator, select parent(s), produce and score a child trail, and expand
 * the goal tree periodically. Re-dispatches itself until a stop condition
 * is met, then hands over to FinalizeAnswer.
 *
 * Deliberate deviation from the original brief: instead of separate
 * Expand/Mutate/Retrieve/Score jobs per action, one step job owns a whole
 * iteration. Per-action jobs would race on the shared candidate pool and
 * make idempotent retries much harder; one-step-per-job keeps the pipeline
 * restartable at every boundary.
 */
class SearchStep extends RunJob
{
    protected function process(Engine $engine, Run $run): void
    {
        $continue = $engine->step($run);

        $this->next($continue ? new SearchStep($run->id) : new FinalizeAnswer($run->id));
    }
}
