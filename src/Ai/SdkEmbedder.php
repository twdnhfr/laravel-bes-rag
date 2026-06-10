<?php

namespace Twdnhfr\BesRag\Ai;

use Laravel\Ai\Embeddings;
use Twdnhfr\BesRag\Contracts\Embedder;

/**
 * Laravel AI SDK backed implementation of the Embedder contract.
 */
class SdkEmbedder implements Embedder
{
    public function __construct(
        protected ?string $provider = null,
        protected ?string $model = null,
    ) {}

    public function embed(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $response = Embeddings::for($texts)->generate($this->provider, $this->model);

        return array_values($response->embeddings);
    }

    public function embedOne(string $text): array
    {
        return $this->embed([$text])[0] ?? [];
    }
}
