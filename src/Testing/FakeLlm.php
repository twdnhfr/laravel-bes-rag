<?php

namespace Twdnhfr\BesRag\Testing;

use Closure;
use Twdnhfr\BesRag\Contracts\Llm;

/**
 * Scripted LLM for tests: queue fixed responses or register handlers that
 * inspect the prompt. No HTTP, fully deterministic.
 */
class FakeLlm implements Llm
{
    protected int $calls = 0;

    /** @var list<string|Closure> */
    protected array $textQueue = [];

    /** @var list<array<string, mixed>|Closure> */
    protected array $structuredQueue = [];

    protected ?Closure $textHandler = null;

    protected ?Closure $structuredHandler = null;

    /** @var list<array{type: string, instructions: string, prompt: string}> */
    public array $log = [];

    public function pushText(string|Closure ...$responses): static
    {
        foreach ($responses as $response) {
            $this->textQueue[] = $response;
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>|Closure  ...$responses
     */
    public function pushStructured(array|Closure ...$responses): static
    {
        foreach ($responses as $response) {
            $this->structuredQueue[] = $response;
        }

        return $this;
    }

    /**
     * Fallback handler: fn (string $instructions, string $prompt): string
     */
    public function onText(Closure $handler): static
    {
        $this->textHandler = $handler;

        return $this;
    }

    /**
     * Fallback handler: fn (string $instructions, string $prompt): array
     */
    public function onStructured(Closure $handler): static
    {
        $this->structuredHandler = $handler;

        return $this;
    }

    public function text(string $instructions, string $prompt, ?string $model = null): string
    {
        $this->calls++;
        $this->log[] = ['type' => 'text', 'instructions' => $instructions, 'prompt' => $prompt];

        if ($this->textQueue !== []) {
            $next = array_shift($this->textQueue);

            return $next instanceof Closure ? (string) $next($instructions, $prompt) : $next;
        }

        if ($this->textHandler !== null) {
            return (string) ($this->textHandler)($instructions, $prompt);
        }

        return 'FAKE RESPONSE';
    }

    public function structured(string $instructions, string $prompt, Closure $schema, ?string $model = null): array
    {
        $this->calls++;
        $this->log[] = ['type' => 'structured', 'instructions' => $instructions, 'prompt' => $prompt];

        if ($this->structuredQueue !== []) {
            $next = array_shift($this->structuredQueue);

            return $next instanceof Closure ? (array) $next($instructions, $prompt) : $next;
        }

        if ($this->structuredHandler !== null) {
            return (array) ($this->structuredHandler)($instructions, $prompt);
        }

        return [];
    }

    public function calls(): int
    {
        return $this->calls;
    }
}
