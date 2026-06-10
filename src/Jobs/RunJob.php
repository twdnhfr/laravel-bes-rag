<?php

namespace Twdnhfr\BesRag\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Twdnhfr\BesRag\Data\BesConfig;
use Twdnhfr\BesRag\Engine\Engine;
use Twdnhfr\BesRag\Engine\EngineFactory;
use Twdnhfr\BesRag\Models\Run;

/**
 * Base class for the pipeline jobs. Payloads carry only the run id —
 * every artifact (goal tree, candidates, evidence, scores) lives in the
 * database, and the engine is rebuilt from the run's persisted config.
 *
 * The queue pipeline resolves the Retriever (and optional Llm/Embedder)
 * from the container, so consuming apps must bind Contracts\Retriever.
 */
abstract class RunJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30, 120];

    public function __construct(public int $runId) {}

    public function handle(EngineFactory $factory): void
    {
        $run = Run::query()->find($this->runId);

        if ($run === null || $run->isFinished()) {
            return;
        }

        $engine = $factory->make(BesConfig::fromArray($run->config_json ?? []));

        $this->process($engine, $run);
    }

    abstract protected function process(Engine $engine, Run $run): void;

    public function failed(?Throwable $exception): void
    {
        $run = Run::query()->find($this->runId);

        if ($run !== null && ! $run->isFinished()) {
            $run->update([
                'status' => Run::STATUS_FAILED,
                'error' => $exception?->getMessage() ?? 'Queue job failed.',
            ]);
        }
    }

    /**
     * Dispatch the next pipeline stage on the configured connection/queue.
     */
    protected function next(RunJob $job): void
    {
        dispatch($job
            ->onConnection(config('bes-rag.queue.connection'))
            ->onQueue(config('bes-rag.queue.queue')));
    }
}
