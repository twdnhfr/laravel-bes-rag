# Changelog

All notable changes to `laravel-bes-rag` will be documented in this file.

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
