<?php

namespace Twdnhfr\BesRag\Data;

/**
 * A candidate in BES-RAG: not just an answer, but the whole trail of
 * search queries, retrieved chunks, selected evidence and synthesis notes
 * that led to it.
 *
 * @phpstan-consistent-constructor
 */
class EvidenceTrail
{
    /** Database id of the bes_candidates row once persisted. */
    public ?int $id = null;

    /** @var list<TrailStep> */
    public array $steps = [];

    public ?string $answerDraft = null;

    public bool $terminal = false;

    public Operation $operation = Operation::Seed;

    /** @var list<int> */
    public array $parentIds = [];

    public float $rawScore = 0.0;

    public float $backwardScore = 0.0;

    public float $effectiveScore = 0.0;

    /** @var array<string, float> goal id => verifier score */
    public array $goalScores = [];

    /** @var array<string, float> raw score components (grounded_answer, citation_support, ...) */
    public array $rawComponents = [];

    /** @var array<string, array<string, mixed>> goal id => verifier reason payload */
    public array $goalScoreReasons = [];

    public function addStep(TrailStep $step): static
    {
        $this->steps[] = $step;

        return $this;
    }

    /**
     * All retrieved chunks in the trail, deduplicated by document/chunk id.
     *
     * @return list<RetrievedChunk>
     */
    public function chunks(): array
    {
        return $this->collectChunks(StepType::RetrievedChunks);
    }

    /**
     * Chunks explicitly selected as evidence (falls back to all retrieved
     * chunks when no explicit selection steps exist).
     *
     * @return list<RetrievedChunk>
     */
    public function selectedEvidence(): array
    {
        $selected = $this->collectChunks(StepType::EvidenceSelection);

        return $selected !== [] ? $selected : $this->chunks();
    }

    /**
     * @return list<array{document_id: string, chunk_id: string}>
     */
    public function citations(): array
    {
        return array_map(
            fn (RetrievedChunk $chunk) => [
                'document_id' => $chunk->documentId,
                'chunk_id' => $chunk->chunkId,
            ],
            $this->selectedEvidence(),
        );
    }

    /**
     * @return list<TrailStep>
     */
    public function stepsForGoal(string $goalId): array
    {
        return array_values(array_filter(
            $this->steps,
            fn (TrailStep $step) => $step->goalId === $goalId,
        ));
    }

    /**
     * Goal ids that have at least one step in this trail, in first-touch order.
     *
     * @return list<string>
     */
    public function touchedGoalIds(): array
    {
        $ids = [];

        foreach ($this->steps as $step) {
            if ($step->goalId !== null && ! in_array($step->goalId, $ids, true)) {
                $ids[] = $step->goalId;
            }
        }

        return $ids;
    }

    /**
     * All search query strings issued in this trail.
     *
     * @return list<string>
     */
    public function queries(): array
    {
        $queries = [];

        foreach ($this->steps as $step) {
            if ($step->type === StepType::SearchQuery) {
                foreach ((array) ($step->content['queries'] ?? []) as $query) {
                    $queries[] = (string) $query;
                }
            }
        }

        return $queries;
    }

    /**
     * Deep copy without id/scores — the starting point for every child trail.
     */
    public function childCopy(Operation $operation): static
    {
        $child = new static;
        $child->operation = $operation;
        $child->parentIds = $this->id !== null ? [$this->id] : [];
        $child->answerDraft = $this->answerDraft;
        $child->terminal = $this->terminal;
        $child->steps = array_map(
            fn (TrailStep $step) => new TrailStep($step->type, $step->content, $step->goalId),
            $this->steps,
        );

        return $child;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'steps' => array_map(fn (TrailStep $step) => $step->toArray(), $this->steps),
            'answer_draft' => $this->answerDraft,
            'terminal' => $this->terminal,
            'operation' => $this->operation->value,
            'parent_ids' => $this->parentIds,
            'raw_score' => $this->rawScore,
            'backward_score' => $this->backwardScore,
            'effective_score' => $this->effectiveScore,
            'goal_scores' => $this->goalScores,
        ];
    }

    /**
     * @return list<RetrievedChunk>
     */
    protected function collectChunks(StepType $type): array
    {
        $chunks = [];

        foreach ($this->steps as $step) {
            if ($step->type !== $type) {
                continue;
            }

            foreach ($step->chunks() as $chunk) {
                $chunks[$chunk->key()] = $chunk;
            }
        }

        return array_values($chunks);
    }
}
