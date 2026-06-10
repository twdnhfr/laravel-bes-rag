<?php

// Config file for twdnhfr/laravel-bes-rag
return [

    /*
    |--------------------------------------------------------------------------
    | AI provider / models
    |--------------------------------------------------------------------------
    |
    | BES-RAG runs on the Laravel AI SDK (laravel/ai). The provider and models
    | configured here are passed straight through to the SDK. `null` uses the
    | SDK's own defaults. Use a strong model for decomposition and synthesis
    | and a fast, cheap model for the many small verifier calls.
    */

    'provider' => env('BES_RAG_PROVIDER'),

    'models' => [
        'decompose' => env('BES_RAG_MODEL_DECOMPOSE'),
        'expand' => env('BES_RAG_MODEL_EXPAND'),
        'verify' => env('BES_RAG_MODEL_VERIFY'),
        'synthesize' => env('BES_RAG_MODEL_SYNTHESIZE'),
        'embeddings' => env('BES_RAG_MODEL_EMBEDDINGS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Search loop
    |--------------------------------------------------------------------------
    */

    'budget' => 30,
    'seed_candidates' => 6,
    'top_k' => 5,
    'max_goal_depth' => 3,
    'decompose_every' => 5,
    'no_progress_limit' => 10,
    'temperature_start' => 1.5,
    'temperature_end' => 0.3,

    // Hard cap on LLM calls per run, independent of the step budget. A single
    // step can trigger several LLM calls (expansion + verifiers), so the step
    // budget alone does not bound cost deterministically.
    'max_llm_calls' => 150,

    'operator_mix' => [
        'expand' => 0.70,
        'combine' => 0.10,
        'delete' => 0.05,
        'translocate' => 0.075,
        'crossover' => 0.075,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoring
    |--------------------------------------------------------------------------
    |
    | Thresholds are relative to the embedding model in use — recalibrate them
    | against an eval set whenever you switch embedders.
    |
    | score_policy: "bucket" (recommended, hard signal dominates, backward
    | score breaks ties within buckets) or "weighted" (simple MVP blend).
    */

    'score_policy' => 'bucket',
    'bucket_size' => 0.1,
    'weighted_raw' => 0.6,
    'weighted_backward' => 0.4,

    // alpha for the recursive goal tree score: weight of a node's own
    // verifier vs. the mean of its children. 0.3 for generic goal trees,
    // 0.7 for strictly sequential multi-hop chains.
    'goal_alpha' => 0.3,

    'raw_score_weights' => [
        'grounded_answer' => 0.35,
        'citation_support' => 0.25,
        'evidence_quality' => 0.20,
        'contradiction_absence' => 0.10,
        'source_diversity' => 0.10,
    ],

    'thresholds' => [
        'semantic_coverage' => 0.72,
        'grounded_answer' => 0.80,
        'citation_support' => 0.80,

        // A goal counts as satisfied once its verifier score reaches this
        // value. Verifiers rarely return a perfect 1.0 on real embedders,
        // so demanding ~1.0 here would make the early-stop unreachable and
        // every run burn its full budget.
        'goal_satisfied' => 0.70,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue pipeline
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'connection' => env('BES_RAG_QUEUE_CONNECTION'),
        'queue' => env('BES_RAG_QUEUE', 'bes-rag'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP API
    |--------------------------------------------------------------------------
    |
    | Disabled by default — the package never registers routes unless you
    | opt in. Add your own auth middleware before enabling this in production.
    */

    'routes' => [
        'enabled' => env('BES_RAG_ROUTES_ENABLED', false),
        'prefix' => 'bes-rag',
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding cache
    |--------------------------------------------------------------------------
    |
    | Embeddings for queries, chunks and goal descriptions are cached so the
    | same text is never embedded twice within a run (or across runs when
    | using a persistent cache store).
    */

    'embedding_cache' => [
        'store' => env('BES_RAG_EMBEDDING_CACHE_STORE'),
        'ttl' => 60 * 60 * 24,
    ],
];
