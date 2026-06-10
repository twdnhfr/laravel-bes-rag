<?php

namespace Twdnhfr\BesRag\Engine;

use Twdnhfr\BesRag\Data\BesConfig;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\GoalTree;
use Twdnhfr\BesRag\Models\Run;

/**
 * Runtime view of a run: the persisted Run model plus the hydrated goal
 * tree and candidate trails the engine works on. All durable state lives
 * on the model / in the repository; this object is rebuilt per process,
 * which is what makes the queue pipeline possible.
 */
class SearchRun
{
    /**
     * @param  list<EvidenceTrail>  $candidates
     */
    public function __construct(
        public Run $model,
        public BesConfig $config,
        public GoalTree $goalTree,
        public array $candidates = [],
    ) {}

    public function question(): string
    {
        return $this->model->question;
    }

    public function addCandidate(EvidenceTrail $trail): void
    {
        $this->candidates[] = $trail;
    }

    /**
     * Linearly annealed Boltzmann temperature over the budget.
     */
    public function temperature(): float
    {
        $budget = max(1, $this->config->budget);
        $progress = min(1.0, $this->model->used_budget / $budget);

        return $this->config->temperatureStart
            + ($this->config->temperatureEnd - $this->config->temperatureStart) * $progress;
    }

    public function best(): ?EvidenceTrail
    {
        $best = null;

        foreach ($this->candidates as $candidate) {
            if ($best === null || $candidate->effectiveScore > $best->effectiveScore) {
                $best = $candidate;
            }
        }

        return $best;
    }

    public function bestTerminal(): ?EvidenceTrail
    {
        $best = null;

        foreach ($this->candidates as $candidate) {
            if (! $candidate->terminal) {
                continue;
            }

            if ($best === null || $candidate->effectiveScore > $best->effectiveScore) {
                $best = $candidate;
            }
        }

        return $best;
    }
}
