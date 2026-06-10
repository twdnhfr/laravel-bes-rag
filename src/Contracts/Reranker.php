<?php

namespace Twdnhfr\BesRag\Contracts;

use Twdnhfr\BesRag\Data\RetrievedChunk;

interface Reranker
{
    /**
     * Re-order (and optionally truncate) retrieved chunks by relevance.
     *
     * @param  list<RetrievedChunk>  $chunks
     * @return list<RetrievedChunk>
     */
    public function rerank(string $query, array $chunks, int $topK): array;
}
