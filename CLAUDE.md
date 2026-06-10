# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`twdnhfr/laravel-bes-rag` is a **Laravel package** (not an application) — a deep RAG search
orchestrator built *on top of* the Laravel AI SDK (`laravel/ai`). It implements the search method
from the BES paper (arXiv:2605.28814) for retrieval: backward goal decomposition, evolving
evidence trails, declarative verifiers and cited answers.

**This is a public, MIT-licensed open-source library** consumed by unknown third-party Laravel
apps. Keep that in mind for every change:

- Build general-purpose, reusable features. No logic tailored to a specific downstream app.
- The public API is a stability contract: `Facades\BesRag`, `PendingSearch`, `BesResult`, the
  `Contracts\*` interfaces, the `Testing\*` fakes and the config keys. Favour additive changes;
  call out anything breaking.
- No hard-coded provider/model assumptions. Provider config flows through `config/bes-rag.php`
  and the fluent builder; the LLM/embedder seams (`Contracts\Llm`, `Contracts\Embedder`) keep
  the SDK swappable and the tests HTTP-free.
- **Never evaluate generated code.** The original BES code uses Python `eval` for verifiers; this
  package deliberately uses registered, declarative verifier classes instead. There is an arch
  test enforcing this.

## Commands

```bash
composer test                  # Pest suite (fast, fully offline — fakes everywhere)
vendor/bin/pest tests/Feature/SyncRunTest.php    # a single file
vendor/bin/pest --filter="multi-hop"             # by description
composer analyse               # PHPStan level 5 (src + database)
vendor/bin/pint                # code style (Laravel preset)
```

Run all three before opening a PR. There is no app to serve; the package is exercised through
its tests.

## Architecture

The load-bearing idea: **every engine phase re-hydrates its state from the database**, so the
same code runs synchronously in one process or distributed across queue jobs.

```
Facades\BesRag → BesRagManager → PendingSearch (fluent builder)
                                     │ answer() = sync        │ dispatch() = queue
                                     ▼                        ▼
                            Engine\EngineFactory      Jobs\StartRun → SeedCandidates
                                     │                  → SearchStep* → FinalizeAnswer
                                     ▼                        (each job rebuilds the
                              Engine\Engine                    engine from run config)
            decompose → seed → step* → finalize
                                     │
                            Engine\RunRepository  ←→  Models (bes_runs, bes_goal_nodes,
                            (DTO ↔ Eloquent bridge)    bes_candidates, bes_candidate_steps,
                                                       bes_evidence_chunks, bes_goal_scores)
```

- **`Data\*`** — plain DTOs: `EvidenceTrail` (the candidate: steps + scores), `GoalTree`/`GoalNode`,
  `BesConfig` (per-run config, serialized onto `bes_runs.config_json`).
- **`Engine\Engine`** — the BES loop, one public method per phase (`decompose`, `seed`, `step`,
  `maybeExpandGoalTree`, `finalize`). `step()` returns false when a stop condition is met
  (step budget, LLM call cap, stagnation, or grounded terminal trail).
- **`Engine\EngineFactory`** — wires everything from `BesConfig` + overrides. Builder passes live
  instances; queue jobs rely on container bindings (`Contracts\Retriever` is mandatory there).
- **`Operators\*`** — expand/combine/delete/translocate/crossover. They work on goal-attributed
  step phases, never raw step indexes, and must return `null` when not applicable.
- **`Scoring\*`** — `RecursiveGoalScorer` (dense backward score, alpha-blend), `RawScoreCalculator`
  (hard signal; LLM-judged components come from the memoized `LlmJudge` — one structured call per
  scored trail), `BucketInterpolatedScorePolicy` (raw dominates, backward breaks ties in-bucket).
- **`Ai\SdkLlm` / `Ai\SdkEmbedder`** — the only places that touch `laravel/ai`. Structured output
  uses the SDK's `StructuredAnonymousAgent` with an `Illuminate JsonSchema` closure.
- **`Testing\FakeLlm` / `FakeEmbedder`** — public test utilities (consumers use them too). The
  whole test suite runs offline through them; `tests/Fixtures/TeslaFixture.php` is the shared
  multi-hop scenario.

## Conventions

- Mirror `twdnhfr/laravel-deepagents` for tooling and structure (Pest 4, PHPStan level 5, Pint,
  spatie/laravel-package-tools, Testbench).
- Migrations as `.stub` under `database/migrations/`; tests load them via
  `defineDatabaseMigrations()` in `tests/TestCase.php`.
- Queue payloads carry only the run id. Anything a job needs must be persisted or
  container-resolvable.
- Document deliberate deviations from the BES brief/paper in code comments where they live
  (e.g. `Jobs\SearchStep` explains why there is one step job instead of per-action jobs).
