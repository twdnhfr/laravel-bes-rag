<?php

namespace Twdnhfr\BesRag;

use Twdnhfr\BesRag\Engine\EngineFactory;
use Twdnhfr\BesRag\Engine\RunRepository;
use Twdnhfr\BesRag\Models\Run;

class BesRagManager
{
    public function __construct(
        protected EngineFactory $factory,
        protected RunRepository $repository,
    ) {}

    /**
     * Start building a deep search.
     */
    public function make(): PendingSearch
    {
        return new PendingSearch($this->factory, $this->repository);
    }

    /**
     * Result handle for an existing run.
     */
    public function result(int|Run $run): BesResult
    {
        $run = $run instanceof Run ? $run : Run::query()->findOrFail($run);

        return new BesResult($run, $this->repository);
    }
}
