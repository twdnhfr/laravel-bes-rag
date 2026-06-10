<?php

namespace Twdnhfr\BesRag\Ai;

use Closure;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Laravel\Ai\StructuredAnonymousAgent;
use Twdnhfr\BesRag\Contracts\Llm;

/**
 * Laravel AI SDK backed implementation of the Llm contract.
 */
class SdkLlm implements Llm
{
    protected int $calls = 0;

    public function __construct(protected ?string $provider = null) {}

    public function text(string $instructions, string $prompt, ?string $model = null): string
    {
        $this->calls++;

        $agent = AnonymousAgent::make($instructions, [], []);

        return $agent->prompt($prompt, provider: $this->provider, model: $model)->text;
    }

    public function structured(string $instructions, string $prompt, Closure $schema, ?string $model = null): array
    {
        $this->calls++;

        $agent = new StructuredAnonymousAgent($instructions, [], [], $schema);

        /** @var StructuredAgentResponse $response */
        $response = $agent->prompt($prompt, provider: $this->provider, model: $model);

        return $response->toArray();
    }

    public function calls(): int
    {
        return $this->calls;
    }
}
