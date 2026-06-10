<?php

namespace Twdnhfr\BesRag\Contracts;

interface Embedder
{
    /**
     * Embed a list of texts into vectors.
     *
     * @param  list<string>  $texts
     * @return list<list<float>> one vector per input, in input order
     */
    public function embed(array $texts): array;

    /**
     * Embed a single text.
     *
     * @return list<float>
     */
    public function embedOne(string $text): array;
}
