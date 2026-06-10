<?php

namespace Twdnhfr\BesRag\Data;

final class TrailStep
{
    /**
     * @param  array<string, mixed>  $content
     */
    public function __construct(
        public StepType $type,
        public array $content = [],
        public ?string $goalId = null,
    ) {}

    /**
     * @return list<RetrievedChunk>
     */
    public function chunks(): array
    {
        if ($this->type !== StepType::RetrievedChunks && $this->type !== StepType::EvidenceSelection) {
            return [];
        }

        return array_map(
            fn (array $chunk) => RetrievedChunk::fromArray($chunk),
            $this->content['chunks'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'content' => $this->content,
            'goal_id' => $this->goalId,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: StepType::from($data['type']),
            content: (array) ($data['content'] ?? []),
            goalId: $data['goal_id'] ?? null,
        );
    }
}
