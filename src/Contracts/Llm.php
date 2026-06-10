<?php

namespace Twdnhfr\BesRag\Contracts;

use Closure;

/**
 * Thin seam over the Laravel AI SDK's text generation.
 *
 * The package ships an SDK-backed implementation (`Ai\SdkLlm`) and a
 * scripted fake for tests (`Testing\FakeLlm`). Consumers normally never
 * implement this themselves.
 */
interface Llm
{
    /**
     * Generate plain text for a prompt.
     */
    public function text(string $instructions, string $prompt, ?string $model = null): string;

    /**
     * Generate a structured (JSON) response validated against a schema.
     *
     * The schema closure follows the Laravel AI SDK convention: it receives
     * an `Illuminate\Contracts\JsonSchema\JsonSchema` builder and returns an
     * `array<string, Type>` property map.
     *
     * @return array<string, mixed>
     */
    public function structured(string $instructions, string $prompt, Closure $schema, ?string $model = null): array;

    /**
     * Number of LLM calls made through this instance.
     */
    public function calls(): int;
}
