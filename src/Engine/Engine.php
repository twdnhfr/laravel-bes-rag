<?php

namespace Twdnhfr\BesRag\Engine;

use Throwable;
use Twdnhfr\BesRag\Contracts\GoalDecomposer;
use Twdnhfr\BesRag\Contracts\Llm;
use Twdnhfr\BesRag\Contracts\SearchPolicy;
use Twdnhfr\BesRag\Data\BesConfig;
use Twdnhfr\BesRag\Events\GoalTreeExpanded;
use Twdnhfr\BesRag\Events\RunCompleted;
use Twdnhfr\BesRag\Events\RunFailed;
use Twdnhfr\BesRag\Events\RunStarted;
use Twdnhfr\BesRag\Events\SearchStepCompleted;
use Twdnhfr\BesRag\Models\Run;
use Twdnhfr\BesRag\Scoring\TrailScorer;

/**
 * The BES search loop, phase by phase. Each phase is independently
 * callable and re-hydrates its state from the database, so the same code
 * runs synchronously in one process or distributed across queue jobs:
 *
 *   decompose → seed → step* → finalize
 */
class Engine
{
    public function __construct(
        protected RunRepository $repository,
        protected GoalDecomposer $decomposer,
        protected TrailExpander $expander,
        protected TrailScorer $scorer,
        protected SearchPolicy $policy,
        protected OperatorMix $operators,
        protected AnswerSynthesizer $synthesizer,
        protected Llm $llm,
    ) {}

    public function repository(): RunRepository
    {
        return $this->repository;
    }

    /**
     * Run every phase synchronously in this process.
     */
    public function run(Run $run): Run
    {
        try {
            $this->decompose($run);
            $this->seed($run);

            while ($this->step($run)) {
                // Each step re-checks the stop conditions.
            }

            $this->finalize($run);
        } catch (Throwable $exception) {
            $this->fail($run, $exception->getMessage());

            throw $exception;
        }

        return $run->refresh();
    }

    /**
     * Phase 1: backward decomposition of the question into a goal tree.
     */
    public function decompose(Run $run): void
    {
        $run->update(['status' => Run::STATUS_DECOMPOSING]);

        event(new RunStarted($run));

        $calls = $this->llm->calls();

        $tree = $this->decomposer->decompose($run->question);

        $this->repository->saveGoalTree($run, $tree);

        $this->recordLlmCalls($run, $calls);

        $run->update(['status' => Run::STATUS_SEARCHING]);
    }

    /**
     * Phase 2: seed initial trails from distinct query strategies.
     */
    public function seed(Run $run): void
    {
        $search = $this->hydrate($run);
        $calls = $this->llm->calls();

        for ($i = 0; $i < $search->config->seedCandidates; $i++) {
            $trail = $this->expander->seed($search, $i);
            $this->scorer->score($search, $trail);
            $this->repository->saveTrail($run, $trail, $search->goalTree);
            $search->addCandidate($trail);
        }

        $best = $search->best();

        $run->update([
            'best_score' => $best !== null ? $best->effectiveScore : 0.0,
        ]);

        $this->recordLlmCalls($run, $calls);
    }

    /**
     * Phase 3 (repeated): one evolution step. Returns false when a stop
     * condition is met and the loop should end.
     */
    public function step(Run $run): bool
    {
        $search = $this->hydrate($run);

        if ($this->shouldStop($search)) {
            return false;
        }

        $calls = $this->llm->calls();

        $operator = $this->operators->sample($search);

        $parents = $operator->arity() >= 2
            ? $this->policy->selectPair($search)
            : [$this->policy->selectParent($search)];

        $child = $operator->apply($search, ...$parents);

        if ($child !== null) {
            $this->scorer->score($search, $child);
            $this->repository->saveTrail($run, $child, $search->goalTree);
            $search->addCandidate($child);
        }

        $best = $search->best();
        $improved = $best !== null && $best->effectiveScore > $run->best_score + 1e-9;

        $run->update([
            'used_budget' => $run->used_budget + 1,
            'best_score' => max($run->best_score, $best !== null ? $best->effectiveScore : 0.0),
            'steps_without_progress' => $improved ? 0 : $run->steps_without_progress + 1,
        ]);

        $this->recordLlmCalls($run, $calls);

        event(new SearchStepCompleted($run, $operator->name(), $child));

        // Periodic backward expansion: refine an unsatisfied goal leaf
        // every K steps, or when the search stagnates.
        $stagnating = $run->steps_without_progress > 0
            && $run->steps_without_progress % max(2, intdiv($search->config->noProgressLimit, 2)) === 0;

        if ($run->used_budget % max(1, $search->config->decomposeEvery) === 0 || $stagnating) {
            $this->maybeExpandGoalTree($run);
        }

        return ! $this->shouldStop($this->hydrate($run));
    }

    /**
     * Phase 4: backward search — decompose the weakest unsatisfied leaf
     * into finer sub-goals and rescore all candidates against the new tree.
     */
    public function maybeExpandGoalTree(Run $run): void
    {
        $search = $this->hydrate($run);
        $best = $search->best();

        if ($best === null) {
            return;
        }

        $unsatisfied = $search->goalTree->unsatisfiedLeaves($best);

        $target = null;
        $lowest = PHP_FLOAT_MAX;

        foreach ($unsatisfied as $leaf) {
            if ($leaf->level >= $search->config->maxGoalDepth) {
                continue;
            }

            $score = $best->goalScores[$leaf->id] ?? 0.0;

            if ($score < $lowest) {
                $lowest = $score;
                $target = $leaf;
            }
        }

        if ($target === null) {
            return;
        }

        $calls = $this->llm->calls();

        $subtree = $this->decomposer->decompose($run->question, $target);

        if ($subtree->roots === []) {
            $this->recordLlmCalls($run, $calls);

            return;
        }

        // The target now has new children (added by the decomposer);
        // persist them and rescore every candidate against the new tree.
        $this->repository->saveGoalTree($run, $search->goalTree);

        foreach ($search->candidates as $candidate) {
            $this->scorer->score($search, $candidate);
            $this->repository->saveTrail($run, $candidate, $search->goalTree);
        }

        $this->recordLlmCalls($run, $calls);

        event(new GoalTreeExpanded($run, $target, count($subtree->roots)));
    }

    /**
     * Phase 5: pick the best sufficiently-grounded terminal trail and
     * synthesize the final answer from its selected evidence only.
     */
    public function finalize(Run $run): void
    {
        $run->update(['status' => Run::STATUS_FINALIZING]);

        $search = $this->hydrate($run);
        $calls = $this->llm->calls();

        $winner = $search->bestTerminal() ?? $search->best();

        if ($winner === null) {
            $this->fail($run, 'Search produced no candidates.');

            return;
        }

        $answer = $this->synthesizer->synthesize($run->question, $winner);

        $this->recordLlmCalls($run, $calls);

        $run->update([
            'status' => Run::STATUS_COMPLETED,
            'final_candidate_id' => $winner->id,
            'answer' => $answer,
        ]);

        event(new RunCompleted($run));
    }

    public function fail(Run $run, string $reason): void
    {
        $run->update([
            'status' => Run::STATUS_FAILED,
            'error' => $reason,
        ]);

        event(new RunFailed($run, $reason));
    }

    public function hydrate(Run $run): SearchRun
    {
        $run->refresh();

        $config = BesConfig::fromArray($run->config_json ?? []);
        $tree = $this->repository->loadGoalTree($run);
        $candidates = $this->repository->loadTrails($run);

        return new SearchRun($run, $config, $tree, $candidates);
    }

    /**
     * Stop conditions per the BES brief: budget exhausted, hard LLM call
     * cap reached, no progress for N steps, or a terminal trail that is
     * grounded above threshold with every goal satisfied.
     */
    public function shouldStop(SearchRun $search): bool
    {
        $run = $search->model;
        $config = $search->config;

        if ($run->used_budget >= $config->budget) {
            return true;
        }

        if ($run->llm_calls >= $config->maxLlmCalls) {
            return true;
        }

        if ($run->steps_without_progress >= $config->noProgressLimit) {
            return true;
        }

        $best = $search->bestTerminal();

        if ($best !== null
            && $best->backwardScore >= 0.999
            && ($best->rawComponents['grounded_answer'] ?? 0.0) >= $config->threshold('grounded_answer', 0.8)) {
            return true;
        }

        return false;
    }

    protected function recordLlmCalls(Run $run, int $callsBefore): void
    {
        $delta = $this->llm->calls() - $callsBefore;

        if ($delta > 0) {
            $run->update(['llm_calls' => $run->llm_calls + $delta]);
        }
    }
}
