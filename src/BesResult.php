<?php

namespace Twdnhfr\BesRag;

use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Engine\RunRepository;
use Twdnhfr\BesRag\Models\Run;

/**
 * Result handle for a finished (or running) BES-RAG run: the cited answer
 * plus the full evidence trail and scores for auditing.
 */
class BesResult
{
    public function __construct(
        protected Run $run,
        protected RunRepository $repository,
    ) {}

    public function run(): Run
    {
        return $this->run->refresh();
    }

    public function id(): int
    {
        return $this->run->id;
    }

    public function status(): string
    {
        return $this->run()->status;
    }

    public function finished(): bool
    {
        return $this->run()->isFinished();
    }

    /**
     * The final cited answer (null while the run is still going or failed).
     */
    public function answer(): ?string
    {
        return $this->run()->answer;
    }

    /**
     * The winning evidence trail — queries, retrieved chunks, selected
     * evidence and notes (null until finalized).
     */
    public function evidenceTrail(): ?EvidenceTrail
    {
        $run = $this->run();

        if ($run->final_candidate_id === null) {
            return null;
        }

        $candidate = $run->finalCandidate()->with('steps')->first();

        return $candidate !== null ? $this->repository->toTrail($candidate) : null;
    }

    /**
     * @return array{raw: float, backward: float, effective: float, components: array<string, float>, goals: array<string, float>}|null
     */
    public function scores(): ?array
    {
        $trail = $this->evidenceTrail();

        if ($trail === null) {
            return null;
        }

        return [
            'raw' => $trail->rawScore,
            'backward' => $trail->backwardScore,
            'effective' => $trail->effectiveScore,
            'components' => $trail->rawComponents,
            'goals' => $trail->goalScores,
        ];
    }

    /**
     * @return list<array{document_id: string, chunk_id: string}>
     */
    public function citations(): array
    {
        return $this->evidenceTrail()?->citations() ?? [];
    }
}
