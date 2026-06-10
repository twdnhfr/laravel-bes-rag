<?php

namespace Twdnhfr\BesRag\Data;

final class RetrievalQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public string $text,
        public array $filters = [],
        public ?string $goalId = null,
    ) {}
}
