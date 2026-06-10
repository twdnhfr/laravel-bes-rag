<?php

namespace Twdnhfr\BesRag\Data;

/**
 * Per-run configuration. Built from config/bes-rag.php, overridable through
 * the fluent builder, and serialized onto the bes_runs row so queued jobs
 * see exactly the configuration the run was started with.
 */
final class BesConfig
{
    /**
     * @param  array<string, float>  $operatorMix
     * @param  array<string, float>  $rawScoreWeights
     * @param  array<string, float>  $thresholds
     * @param  array<string, string|null>  $models
     * @param  array<string, mixed>  $retrievalContext
     */
    public function __construct(
        public int $budget = 30,
        public int $seedCandidates = 6,
        public int $topK = 5,
        public int $maxGoalDepth = 3,
        public int $decomposeEvery = 5,
        public int $noProgressLimit = 10,
        public float $temperatureStart = 1.5,
        public float $temperatureEnd = 0.3,
        public int $maxLlmCalls = 150,
        public array $operatorMix = [
            'expand' => 0.70,
            'combine' => 0.10,
            'delete' => 0.05,
            'translocate' => 0.075,
            'crossover' => 0.075,
        ],
        public string $scorePolicy = 'bucket',
        public float $bucketSize = 0.1,
        public float $weightedRaw = 0.6,
        public float $weightedBackward = 0.4,
        public float $goalAlpha = 0.3,
        public array $rawScoreWeights = [
            'grounded_answer' => 0.35,
            'citation_support' => 0.25,
            'evidence_quality' => 0.20,
            'contradiction_absence' => 0.10,
            'source_diversity' => 0.10,
        ],
        public array $thresholds = [
            'semantic_coverage' => 0.72,
            'grounded_answer' => 0.80,
            'citation_support' => 0.80,
        ],
        public ?string $provider = null,
        public array $models = [],
        public array $retrievalContext = [],
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $defaults = new self;

        return new self(
            budget: (int) ($config['budget'] ?? $defaults->budget),
            seedCandidates: (int) ($config['seed_candidates'] ?? $defaults->seedCandidates),
            topK: (int) ($config['top_k'] ?? $defaults->topK),
            maxGoalDepth: (int) ($config['max_goal_depth'] ?? $defaults->maxGoalDepth),
            decomposeEvery: (int) ($config['decompose_every'] ?? $defaults->decomposeEvery),
            noProgressLimit: (int) ($config['no_progress_limit'] ?? $defaults->noProgressLimit),
            temperatureStart: (float) ($config['temperature_start'] ?? $defaults->temperatureStart),
            temperatureEnd: (float) ($config['temperature_end'] ?? $defaults->temperatureEnd),
            maxLlmCalls: (int) ($config['max_llm_calls'] ?? $defaults->maxLlmCalls),
            operatorMix: (array) ($config['operator_mix'] ?? $defaults->operatorMix),
            scorePolicy: (string) ($config['score_policy'] ?? $defaults->scorePolicy),
            bucketSize: (float) ($config['bucket_size'] ?? $defaults->bucketSize),
            weightedRaw: (float) ($config['weighted_raw'] ?? $defaults->weightedRaw),
            weightedBackward: (float) ($config['weighted_backward'] ?? $defaults->weightedBackward),
            goalAlpha: (float) ($config['goal_alpha'] ?? $defaults->goalAlpha),
            rawScoreWeights: (array) ($config['raw_score_weights'] ?? $defaults->rawScoreWeights),
            thresholds: (array) ($config['thresholds'] ?? $defaults->thresholds),
            provider: $config['provider'] ?? null,
            models: (array) ($config['models'] ?? []),
            retrievalContext: (array) ($config['retrieval_context'] ?? []),
        );
    }

    public function model(string $purpose): ?string
    {
        return $this->models[$purpose] ?? null;
    }

    public function threshold(string $key, float $default = 0.72): float
    {
        return (float) ($this->thresholds[$key] ?? $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'budget' => $this->budget,
            'seed_candidates' => $this->seedCandidates,
            'top_k' => $this->topK,
            'max_goal_depth' => $this->maxGoalDepth,
            'decompose_every' => $this->decomposeEvery,
            'no_progress_limit' => $this->noProgressLimit,
            'temperature_start' => $this->temperatureStart,
            'temperature_end' => $this->temperatureEnd,
            'max_llm_calls' => $this->maxLlmCalls,
            'operator_mix' => $this->operatorMix,
            'score_policy' => $this->scorePolicy,
            'bucket_size' => $this->bucketSize,
            'weighted_raw' => $this->weightedRaw,
            'weighted_backward' => $this->weightedBackward,
            'goal_alpha' => $this->goalAlpha,
            'raw_score_weights' => $this->rawScoreWeights,
            'thresholds' => $this->thresholds,
            'provider' => $this->provider,
            'models' => $this->models,
            'retrieval_context' => $this->retrievalContext,
        ];
    }
}
