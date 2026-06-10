<?php

use Twdnhfr\BesRag\Contracts\Embedder;
use Twdnhfr\BesRag\Data\RetrievalQuery;
use Twdnhfr\BesRag\Data\RetrievedChunk;
use Twdnhfr\BesRag\Retrieval\ArrayRetriever;
use Twdnhfr\BesRag\Retrieval\EmbeddingCache;
use Twdnhfr\BesRag\Support\Vectors;
use Twdnhfr\BesRag\Testing\FakeEmbedder;

it('ranks chunks by token overlap', function () {
    $retriever = new ArrayRetriever([
        new RetrievedChunk('doc1', 'c1', 'Tesla produces the Model S electric sedan'),
        new RetrievedChunk('doc2', 'c1', 'Bananas are yellow fruit'),
        new RetrievedChunk('doc3', 'c1', 'The Model S is produced by Tesla in Fremont'),
    ]);

    $results = $retriever->retrieve(new RetrievalQuery('who produces the Model S'), 2);

    expect($results)->toHaveCount(2)
        ->and(array_map(fn ($chunk) => $chunk->documentId, $results))->not->toContain('doc2');
});

it('embeds each unique text only once', function () {
    $counting = new class implements Embedder
    {
        public int $calls = 0;

        public function embed(array $texts): array
        {
            $this->calls += count($texts);

            return array_map(fn () => [1.0, 0.0], $texts);
        }

        public function embedOne(string $text): array
        {
            return $this->embed([$text])[0];
        }
    };

    $cache = new EmbeddingCache($counting);

    $cache->embed(['alpha', 'beta']);
    $cache->embed(['alpha', 'gamma']);
    $cache->embedOne('beta');

    expect($counting->calls)->toBe(3); // alpha, beta, gamma — each once
});

it('produces similar vectors for overlapping texts', function () {
    $embedder = new FakeEmbedder;

    $a = $embedder->embedOne('tesla model s production');
    $b = $embedder->embedOne('production of the tesla model s');
    $c = $embedder->embedOne('bananas are yellow');

    $cosine = fn (array $x, array $y) => Vectors::cosine($x, $y);

    expect($cosine($a, $b))->toBeGreaterThan(0.7)
        ->and($cosine($a, $c))->toBeLessThan(0.3);
});
