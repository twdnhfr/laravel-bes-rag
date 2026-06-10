<?php

namespace Twdnhfr\BesRag\Retrieval;

use Twdnhfr\BesRag\Contracts\Retriever;
use Twdnhfr\BesRag\Data\RetrievalQuery;
use Twdnhfr\BesRag\Data\RetrievedChunk;

/**
 * In-memory retriever over a fixed corpus, scored by token overlap.
 * Useful for tests, demos and small document sets — and the reference
 * implementation for writing your own Retriever adapter.
 */
class ArrayRetriever implements Retriever
{
    /** @var list<RetrievedChunk> */
    protected array $corpus = [];

    /**
     * @param  list<RetrievedChunk|array<string, mixed>>  $chunks
     */
    public function __construct(array $chunks = [])
    {
        foreach ($chunks as $chunk) {
            $this->add($chunk instanceof RetrievedChunk ? $chunk : RetrievedChunk::fromArray($chunk));
        }
    }

    public function add(RetrievedChunk $chunk): static
    {
        $this->corpus[] = $chunk;

        return $this;
    }

    public function retrieve(RetrievalQuery $query, int $topK = 5): array
    {
        $queryTokens = $this->tokens($query->text);

        if ($queryTokens === []) {
            return [];
        }

        $scored = [];

        foreach ($this->corpus as $chunk) {
            $chunkTokens = $this->tokens($chunk->text);
            $overlap = count(array_intersect($queryTokens, $chunkTokens));

            if ($overlap === 0) {
                continue;
            }

            $score = $overlap / count($queryTokens);

            $scored[] = new RetrievedChunk(
                documentId: $chunk->documentId,
                chunkId: $chunk->chunkId,
                text: $chunk->text,
                metadata: $chunk->metadata,
                score: $score,
            );
        }

        usort($scored, fn (RetrievedChunk $a, RetrievedChunk $b) => $b->score <=> $a->score);

        return array_slice($scored, 0, $topK);
    }

    /**
     * @return list<string>
     */
    protected function tokens(string $text): array
    {
        return array_values(array_unique(
            preg_split('/[^a-z0-9]+/', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ));
    }
}
