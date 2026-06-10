<?php

namespace Twdnhfr\BesRag;

use Closure;
use Twdnhfr\BesRag\Contracts\Embedder;
use Twdnhfr\BesRag\Contracts\EvolutionOperator;
use Twdnhfr\BesRag\Contracts\GoalDecomposer;
use Twdnhfr\BesRag\Contracts\Llm;
use Twdnhfr\BesRag\Contracts\Reranker;
use Twdnhfr\BesRag\Contracts\Retriever;
use Twdnhfr\BesRag\Contracts\ScorePolicy;
use Twdnhfr\BesRag\Contracts\SearchPolicy;
use Twdnhfr\BesRag\Contracts\Verifier;
use Twdnhfr\BesRag\Data\BesConfig;
use Twdnhfr\BesRag\Engine\EngineFactory;
use Twdnhfr\BesRag\Engine\RunRepository;
use Twdnhfr\BesRag\Jobs\StartRun;

/**
 * The public fluent builder:
 *
 *     $result = BesRag::make()
 *         ->retriever($retriever)
 *         ->budget(30)
 *         ->maxDepth(3)
 *         ->answer($question);     // synchronous
 *
 *     $result = BesRag::make()->dispatch($question);  // queue pipeline
 */
class PendingSearch
{
    /** @var array<string, mixed> */
    protected array $configOverrides = [];

    /** @var array<string, mixed> */
    protected array $overrides = [];

    public function __construct(
        protected EngineFactory $factory,
        protected RunRepository $repository,
    ) {}

    public function retriever(Retriever $retriever): static
    {
        $this->overrides['retriever'] = $retriever;

        return $this;
    }

    public function llm(Llm $llm): static
    {
        $this->overrides['llm'] = $llm;

        return $this;
    }

    public function embedder(Embedder $embedder): static
    {
        $this->overrides['embedder'] = $embedder;

        return $this;
    }

    public function reranker(Reranker $reranker): static
    {
        $this->overrides['reranker'] = $reranker;

        return $this;
    }

    public function decomposer(GoalDecomposer $decomposer): static
    {
        $this->overrides['decomposer'] = $decomposer;

        return $this;
    }

    public function searchPolicy(SearchPolicy $policy): static
    {
        $this->overrides['searchPolicy'] = $policy;

        return $this;
    }

    public function scorePolicy(ScorePolicy $policy): static
    {
        $this->overrides['scorePolicy'] = $policy;

        return $this;
    }

    public function verifier(string $type, Verifier $verifier): static
    {
        $this->overrides['verifiers'][$type] = $verifier;

        return $this;
    }

    /**
     * @param  list<EvolutionOperator>  $operators
     */
    public function operators(array $operators): static
    {
        $this->overrides['operators'] = $operators;

        return $this;
    }

    /**
     * Inject a deterministic random source (tests).
     *
     * @param  Closure(): float  $random
     */
    public function random(Closure $random): static
    {
        $this->overrides['random'] = $random;

        return $this;
    }

    public function provider(string $provider): static
    {
        $this->configOverrides['provider'] = $provider;

        return $this;
    }

    /**
     * Override a model per purpose: decompose, expand, verify, synthesize,
     * embeddings.
     */
    public function model(string $purpose, string $model): static
    {
        $this->configOverrides['models'][$purpose] = $model;

        return $this;
    }

    public function budget(int $budget): static
    {
        $this->configOverrides['budget'] = $budget;

        return $this;
    }

    public function seedCandidates(int $count): static
    {
        $this->configOverrides['seed_candidates'] = $count;

        return $this;
    }

    public function topK(int $topK): static
    {
        $this->configOverrides['top_k'] = $topK;

        return $this;
    }

    public function maxDepth(int $depth): static
    {
        $this->configOverrides['max_goal_depth'] = $depth;

        return $this;
    }

    public function decomposeEvery(int $steps): static
    {
        $this->configOverrides['decompose_every'] = $steps;

        return $this;
    }

    public function noProgressLimit(int $steps): static
    {
        $this->configOverrides['no_progress_limit'] = $steps;

        return $this;
    }

    public function maxLlmCalls(int $calls): static
    {
        $this->configOverrides['max_llm_calls'] = $calls;

        return $this;
    }

    public function temperature(float $start, float $end): static
    {
        $this->configOverrides['temperature_start'] = $start;
        $this->configOverrides['temperature_end'] = $end;

        return $this;
    }

    /**
     * App-side scoping for every retrieval of this run (e.g. tenant or
     * collection ids). Persisted with the run config, so the queue pipeline
     * sees it too; delivered to the retriever as RetrievalQuery->filters.
     *
     * @param  array<string, mixed>  $context
     */
    public function retrievalContext(array $context): static
    {
        $this->configOverrides['retrieval_context'] = $context;

        return $this;
    }

    /**
     * @param  array<string, float>  $mix
     */
    public function operatorMix(array $mix): static
    {
        $this->configOverrides['operator_mix'] = $mix;

        return $this;
    }

    /**
     * Arbitrary config overrides (keys as in config/bes-rag.php).
     *
     * @param  array<string, mixed>  $overrides
     */
    public function withConfig(array $overrides): static
    {
        $this->configOverrides = array_replace_recursive($this->configOverrides, $overrides);

        return $this;
    }

    /**
     * Run the full search synchronously and return the finished result.
     */
    public function answer(string $question): BesResult
    {
        $config = $this->buildConfig();
        $run = $this->repository->createRun($question, $config);

        $engine = $this->factory->make($config, $this->overrides);
        $engine->run($run);

        return new BesResult($run, $this->repository);
    }

    /**
     * Start the run on the queue pipeline and return immediately. Requires
     * Contracts\Retriever (and any other instance overrides you would have
     * passed) to be resolvable from the container, because queue workers
     * cannot serialize live instances.
     */
    public function dispatch(string $question): BesResult
    {
        $config = $this->buildConfig();
        $run = $this->repository->createRun($question, $config);

        StartRun::dispatch($run->id)
            ->onConnection(config('bes-rag.queue.connection'))
            ->onQueue(config('bes-rag.queue.queue'));

        return new BesResult($run, $this->repository);
    }

    protected function buildConfig(): BesConfig
    {
        $base = (array) config('bes-rag', []);

        return BesConfig::fromArray(array_replace_recursive($base, $this->configOverrides));
    }
}
