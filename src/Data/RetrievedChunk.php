<?php

namespace Twdnhfr\BesRag\Data;

final class RetrievedChunk
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $documentId,
        public string $chunkId,
        public string $text,
        public array $metadata = [],
        public float $score = 0.0,
    ) {}

    /**
     * Stable identity for deduplication across trails.
     */
    public function key(): string
    {
        return $this->documentId.'/'.$this->chunkId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'document_id' => $this->documentId,
            'chunk_id' => $this->chunkId,
            'text' => $this->text,
            'metadata' => $this->metadata,
            'score' => $this->score,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            documentId: (string) ($data['document_id'] ?? ''),
            chunkId: (string) ($data['chunk_id'] ?? ''),
            text: (string) ($data['text'] ?? ''),
            metadata: (array) ($data['metadata'] ?? []),
            score: (float) ($data['score'] ?? 0.0),
        );
    }
}
