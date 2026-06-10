<?php

namespace Twdnhfr\BesRag\Retrieval;

use Illuminate\Contracts\Cache\Repository as Cache;
use Twdnhfr\BesRag\Contracts\Embedder;

/**
 * Caching decorator around any Embedder: an in-process array cache for the
 * hot path (queries, goal descriptions and chunks recur constantly within
 * a run) plus an optional Laravel cache store for cross-run reuse.
 */
class EmbeddingCache implements Embedder
{
    /** @var array<string, list<float>> */
    protected array $local = [];

    public function __construct(
        protected Embedder $inner,
        protected ?Cache $store = null,
        protected int $ttl = 86400,
    ) {}

    public function embed(array $texts): array
    {
        $vectors = [];
        $missing = [];

        foreach ($texts as $index => $text) {
            $cached = $this->get($text);

            if ($cached !== null) {
                $vectors[$index] = $cached;
            } else {
                $missing[$index] = $text;
            }
        }

        if ($missing !== []) {
            $fresh = $this->inner->embed(array_values($missing));

            foreach (array_keys($missing) as $position => $index) {
                $vector = $fresh[$position] ?? [];
                $vectors[$index] = $vector;
                $this->put($missing[$index], $vector);
            }
        }

        ksort($vectors);

        return array_values($vectors);
    }

    public function embedOne(string $text): array
    {
        return $this->embed([$text])[0] ?? [];
    }

    protected function key(string $text): string
    {
        return 'bes-rag:embedding:'.hash('sha256', $text);
    }

    protected function get(string $text): ?array
    {
        $key = $this->key($text);

        if (isset($this->local[$key])) {
            return $this->local[$key];
        }

        $stored = $this->store?->get($key);

        if (is_array($stored)) {
            $this->local[$key] = $stored;

            return $stored;
        }

        return null;
    }

    /**
     * @param  list<float>  $vector
     */
    protected function put(string $text, array $vector): void
    {
        $key = $this->key($text);
        $this->local[$key] = $vector;
        $this->store?->put($key, $vector, $this->ttl);
    }
}
