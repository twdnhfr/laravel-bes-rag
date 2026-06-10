<?php

namespace Twdnhfr\BesRag\Scoring;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Twdnhfr\BesRag\Contracts\Llm;
use Twdnhfr\BesRag\Data\EvidenceTrail;

/**
 * One LLM call assessing groundedness, citation support and contradictions
 * for a trail's answer draft against its selected evidence. Bundling the
 * three judgements into a single structured call keeps verifier cost at
 * one call per scored trail instead of three.
 *
 * Results are memoized per (answer, evidence) pair, so re-scoring an
 * unchanged trail is free.
 */
class LlmJudge
{
    /** @var array<string, array{grounded: float, citation_support: float, contradiction_absence: float, notes: string}> */
    protected array $cache = [];

    public function __construct(
        protected Llm $llm,
        protected ?string $model = null,
    ) {}

    /**
     * @return array{grounded: float, citation_support: float, contradiction_absence: float, notes: string}
     */
    public function assess(string $question, EvidenceTrail $trail): array
    {
        if ($trail->answerDraft === null || trim($trail->answerDraft) === '') {
            return [
                'grounded' => 0.0,
                'citation_support' => 0.0,
                'contradiction_absence' => 1.0,
                'notes' => 'no answer draft yet',
            ];
        }

        $evidence = $this->evidenceBlock($trail);
        $key = hash('sha256', $trail->answerDraft."\x00".$evidence);

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $result = $this->llm->structured(
            $this->instructions(),
            "Question:\n{$question}\n\nDraft answer:\n{$trail->answerDraft}\n\nEvidence chunks:\n{$evidence}",
            $this->schema(...),
            $this->model,
        );

        return $this->cache[$key] = [
            'grounded' => $this->clamp($result['grounded'] ?? 0),
            'citation_support' => $this->clamp($result['citation_support'] ?? 0),
            'contradiction_absence' => $this->clamp($result['contradiction_absence'] ?? 1),
            'notes' => (string) ($result['notes'] ?? ''),
        ];
    }

    protected function evidenceBlock(EvidenceTrail $trail): string
    {
        $blocks = [];

        foreach ($trail->selectedEvidence() as $chunk) {
            $blocks[] = "[{$chunk->documentId}/{$chunk->chunkId}]\n".mb_substr($chunk->text, 0, 1500);
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @return array<string, Type>
     */
    protected function schema(JsonSchema $schema): array
    {
        return [
            'grounded' => $schema->number()->description('0-1: every claim in the answer is derivable from the evidence')->required(),
            'citation_support' => $schema->number()->description('0-1: fraction of answer claims a specific evidence chunk supports')->required(),
            'contradiction_absence' => $schema->number()->description('0-1: 1 means no evidence chunk contradicts the answer')->required(),
            'notes' => $schema->string()->description('One sentence: weakest spot of the answer'),
        ];
    }

    protected function instructions(): string
    {
        return <<<'PROMPT'
        You are a strict evidence auditor for a retrieval system. Judge the draft
        answer ONLY against the provided evidence chunks — outside knowledge must
        not raise any score. Be conservative: unsupported claims lower both
        grounded and citation_support.
        PROMPT;
    }

    protected function clamp(mixed $value): float
    {
        return max(0.0, min(1.0, (float) $value));
    }
}
