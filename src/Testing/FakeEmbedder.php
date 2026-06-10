<?php

namespace Twdnhfr\BesRag\Testing;

use Twdnhfr\BesRag\Contracts\Embedder;

/**
 * Deterministic bag-of-words embedder for tests: token overlap produces
 * high cosine similarity, disjoint texts produce low similarity. Crude,
 * but it behaves directionally like a real embedding model without any
 * network dependency.
 */
class FakeEmbedder implements Embedder
{
    public function __construct(protected int $dimensions = 64) {}

    public function embed(array $texts): array
    {
        return array_map(fn (string $text) => $this->embedOne($text), $texts);
    }

    public function embedOne(string $text): array
    {
        $vector = array_fill(0, $this->dimensions, 0.0);

        $tokens = preg_split('/[^a-z0-9]+/', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            $vector[crc32($token) % $this->dimensions] += 1.0;
        }

        $norm = sqrt(array_sum(array_map(fn (float $v) => $v * $v, $vector)));

        if ($norm > 0) {
            $vector = array_map(fn (float $v) => $v / $norm, $vector);
        }

        return $vector;
    }
}
