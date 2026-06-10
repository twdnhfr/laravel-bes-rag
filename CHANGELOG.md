# Changelog

All notable changes to `laravel-bes-rag` will be documented in this file.

## v0.2.1 - 2026-06-10

- Requires `laravel/ai` ^0.8.0 (dependency bump; no code changes). Same fix set as v0.2.0, re-tagged on top of the dependency update because the published v0.2.0 reference is immutable on Packagist.

## v0.2.0 - 2026-06-10

Fixed — runs always burned their full step budget, even on simple questions:

- New `thresholds.goal_satisfied` (default 0.70): a goal now counts as satisfied at this verifier score instead of requiring ~1.0, which real embedders/judges almost never return. Applied consistently in the goal scorer (satisfaction snapping), the forward-search frontier (`openGoals`) and dependency gating.
- The grounded early-stop checks the live frontier (`openGoals === []`) instead of the unreachable `backward_score >= 0.999`.
- Backward goal-tree expansion is now guarded: it only refines leaves the best trail has actually worked on and still failed, and is skipped when the remaining budget cannot cover the current frontier anyway — previously the tree grew faster than trails could satisfy it, making termination impossible.
- `RecursiveGoalScorer` constructor: `satisfiedEpsilon` replaced by `satisfiedThreshold` (default 0.7).

## v0.1.0 - 2026-06-10

Initial release:

- BES search loop (decompose → seed → evolve → finalize) with budget, LLM call cap and stagnation stop conditions
- Backward decomposition into checkable sub-goals with declarative verifiers (semantic coverage, evidence presence, citation support, entity match, contradiction check, dependency gating)
- Evidence trails as candidates with expand / combine / delete / translocate / crossover operators (goal-boundary aware)
- Boltzmann parent selection with temperature annealing and complementarity-based pair selection
- Bucket-interpolated scoring (groundedness dominates, backward score breaks ties) and weighted alternative
- Persistence of runs, goal trees, candidates, steps, evidence chunks and goal scores
- Sync engine and queue pipeline (`StartRun → SeedCandidates → SearchStep* → FinalizeAnswer`)
- Opt-in HTTP API: deep-answer, run status, debug trace, SSE progress stream
- Laravel AI SDK integration (text, structured output, embeddings) with embedding cache
- Testing utilities: `FakeLlm`, `FakeEmbedder`, `ArrayRetriever`
- Per-run retrieval context (`->retrievalContext([...])`) delivered to the retriever as `RetrievalQuery->filters`, persisted with the run for the queue pipeline
