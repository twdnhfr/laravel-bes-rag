<?php

namespace Twdnhfr\BesRag\Engine;

use Closure;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Container\Container;
use RuntimeException;
use Throwable;
use Twdnhfr\BesRag\Ai\SdkEmbedder;
use Twdnhfr\BesRag\Ai\SdkLlm;
use Twdnhfr\BesRag\Contracts\Embedder;
use Twdnhfr\BesRag\Contracts\GoalDecomposer;
use Twdnhfr\BesRag\Contracts\Llm;
use Twdnhfr\BesRag\Contracts\Reranker;
use Twdnhfr\BesRag\Contracts\Retriever;
use Twdnhfr\BesRag\Contracts\ScorePolicy;
use Twdnhfr\BesRag\Contracts\SearchPolicy;
use Twdnhfr\BesRag\Data\BesConfig;
use Twdnhfr\BesRag\Decomposition\LlmGoalDecomposer;
use Twdnhfr\BesRag\Operators\CombineOperator;
use Twdnhfr\BesRag\Operators\CrossoverOperator;
use Twdnhfr\BesRag\Operators\DeleteOperator;
use Twdnhfr\BesRag\Operators\ExpandOperator;
use Twdnhfr\BesRag\Operators\TranslocateOperator;
use Twdnhfr\BesRag\Retrieval\EmbeddingCache;
use Twdnhfr\BesRag\Scoring\BucketInterpolatedScorePolicy;
use Twdnhfr\BesRag\Scoring\LlmJudge;
use Twdnhfr\BesRag\Scoring\RawScoreCalculator;
use Twdnhfr\BesRag\Scoring\RecursiveGoalScorer;
use Twdnhfr\BesRag\Scoring\TrailScorer;
use Twdnhfr\BesRag\Scoring\VerifierRegistry;
use Twdnhfr\BesRag\Scoring\Verifiers\CitationSupportVerifier;
use Twdnhfr\BesRag\Scoring\Verifiers\ContradictionVerifier;
use Twdnhfr\BesRag\Scoring\Verifiers\DependencySatisfiedVerifier;
use Twdnhfr\BesRag\Scoring\Verifiers\EntityMatchVerifier;
use Twdnhfr\BesRag\Scoring\Verifiers\EvidencePresenceVerifier;
use Twdnhfr\BesRag\Scoring\Verifiers\SemanticCoverageVerifier;
use Twdnhfr\BesRag\Scoring\WeightedScorePolicy;

/**
 * Wires an Engine from configuration. The fluent builder passes explicit
 * overrides (retriever, llm, ...); queue jobs call it with only the run's
 * persisted config and rely on container bindings — which is why apps
 * using the queue pipeline must bind Contracts\Retriever.
 *
 * @phpstan-type Overrides array{
 *   retriever?: Retriever,
 *   llm?: Llm,
 *   embedder?: Embedder,
 *   reranker?: Reranker,
 *   decomposer?: GoalDecomposer,
 *   searchPolicy?: SearchPolicy,
 *   scorePolicy?: ScorePolicy,
 *   verifiers?: array<string, \Twdnhfr\BesRag\Contracts\Verifier>,
 *   operators?: list<\Twdnhfr\BesRag\Contracts\EvolutionOperator>,
 *   random?: Closure(): float,
 * }
 */
class EngineFactory
{
    public function __construct(protected Container $container) {}

    /**
     * @param  Overrides  $overrides
     */
    public function make(BesConfig $config, array $overrides = []): Engine
    {
        $llm = $overrides['llm']
            ?? ($this->container->bound(Llm::class) ? $this->container->make(Llm::class) : new SdkLlm($config->provider));

        $embedder = $overrides['embedder']
            ?? ($this->container->bound(Embedder::class)
                ? $this->container->make(Embedder::class)
                : new SdkEmbedder($config->provider, $config->model('embeddings')));

        if (! $embedder instanceof EmbeddingCache) {
            $embedder = new EmbeddingCache($embedder, $this->embeddingCacheStore());
        }

        $retriever = $overrides['retriever']
            ?? ($this->container->bound(Retriever::class) ? $this->container->make(Retriever::class) : null);

        if ($retriever === null) {
            throw new RuntimeException(
                'No retriever available. Pass one via BesRag::make()->retriever(...) or bind '
                .Retriever::class.' in the container (required for the queue pipeline).',
            );
        }

        $judge = new LlmJudge($llm, $config->model('verify'));

        $registry = new VerifierRegistry;
        $registry->register('semantic_query_coverage', new SemanticCoverageVerifier($embedder, $config->threshold('semantic_coverage')));
        $registry->register('evidence_presence', new EvidencePresenceVerifier);
        $registry->register('entity_match', new EntityMatchVerifier);
        $registry->register('citation_support', new CitationSupportVerifier($judge));
        $registry->register('contradiction_check', new ContradictionVerifier($judge));
        $registry->register('dependency_satisfied', new DependencySatisfiedVerifier);

        foreach ($overrides['verifiers'] ?? [] as $type => $verifier) {
            $registry->register($type, $verifier);
        }

        $scorePolicy = $overrides['scorePolicy'] ?? match ($config->scorePolicy) {
            'weighted' => new WeightedScorePolicy($config->weightedRaw, $config->weightedBackward),
            default => new BucketInterpolatedScorePolicy($config->bucketSize),
        };

        $scorer = new TrailScorer(
            new RecursiveGoalScorer($registry, $config->goalAlpha),
            new RawScoreCalculator($judge),
            $scorePolicy,
        );

        $synthesizer = new AnswerSynthesizer($llm, $config->model('synthesize'));

        $reranker = $overrides['reranker']
            ?? ($this->container->bound(Reranker::class) ? $this->container->make(Reranker::class) : null);

        $expander = new TrailExpander(
            $retriever,
            $llm,
            $synthesizer,
            $reranker,
            $config->model('expand'),
        );

        $decomposer = $overrides['decomposer'] ?? new LlmGoalDecomposer($llm, $registry, $config->model('decompose'));

        $random = $overrides['random'] ?? null;

        $operators = $overrides['operators'] ?? [
            new ExpandOperator($expander),
            new CombineOperator,
            new DeleteOperator,
            new TranslocateOperator,
            new CrossoverOperator,
        ];

        return new Engine(
            $this->container->make(RunRepository::class),
            $decomposer,
            $expander,
            $scorer,
            $overrides['searchPolicy'] ?? new BoltzmannSelection($random),
            new OperatorMix($operators, $config->operatorMix, $random),
            $synthesizer,
            $llm,
        );
    }

    protected function embeddingCacheStore(): ?Repository
    {
        try {
            /** @var Factory $cache */
            $cache = $this->container->make('cache');

            return $cache->store(config('bes-rag.embedding_cache.store'));
        } catch (Throwable) {
            return null;
        }
    }
}
