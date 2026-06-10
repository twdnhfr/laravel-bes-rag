<?php

namespace Twdnhfr\BesRag\Engine;

use Twdnhfr\BesRag\Contracts\Llm;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\StepType;

/**
 * Generates answers strictly from a trail's selected evidence — the model
 * is instructed to cite chunk ids and to refuse claims the evidence does
 * not back.
 */
class AnswerSynthesizer
{
    public function __construct(
        protected Llm $llm,
        protected ?string $model = null,
    ) {}

    public function synthesize(string $question, EvidenceTrail $trail): string
    {
        $evidence = [];

        foreach ($trail->selectedEvidence() as $chunk) {
            $evidence[] = "[{$chunk->documentId}/{$chunk->chunkId}]\n".mb_substr($chunk->text, 0, 1500);
        }

        $notes = [];

        foreach ($trail->steps as $step) {
            if ($step->type === StepType::SynthesisNote) {
                $notes[] = (string) ($step->content['note'] ?? '');
            }
        }

        $prompt = "Question:\n{$question}\n\n"
            ."Evidence chunks (cite them as [document/chunk]):\n".implode("\n\n", $evidence)
            .($notes !== [] ? "\n\nResearch notes so far:\n- ".implode("\n- ", array_filter($notes)) : '');

        return $this->llm->text($this->instructions(), $prompt, $this->model);
    }

    protected function instructions(): string
    {
        return <<<'PROMPT'
        Answer the question using ONLY the provided evidence chunks. Rules:
        - Cite the supporting chunk inline as [document_id/chunk_id] after every claim.
        - If the evidence does not cover part of the question, say so explicitly instead of guessing.
        - Be concise and factual.
        PROMPT;
    }
}
