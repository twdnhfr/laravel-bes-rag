<?php

namespace Twdnhfr\BesRag\Contracts;

use Twdnhfr\BesRag\Data\RetrievalQuery;
use Twdnhfr\BesRag\Data\RetrievedChunk;

/**
 * Adapter to the consuming application's vector store / search index.
 *
 * This is the one contract every consumer must implement (or use one of
 * the shipped implementations).
 */
interface Retriever
{
    /**
     * @return list<RetrievedChunk>
     */
    public function retrieve(RetrievalQuery $query, int $topK = 5): array;
}
