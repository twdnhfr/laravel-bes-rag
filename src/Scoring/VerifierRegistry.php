<?php

namespace Twdnhfr\BesRag\Scoring;

use Closure;
use InvalidArgumentException;
use Twdnhfr\BesRag\Contracts\Verifier;

/**
 * Maps declarative verifier type strings (as emitted by the decomposer)
 * to Verifier instances. Consumers can register their own types.
 */
class VerifierRegistry
{
    /** @var array<string, Verifier|Closure(): Verifier> */
    protected array $verifiers = [];

    /** @var array<string, Verifier> */
    protected array $resolved = [];

    /**
     * @param  Verifier|Closure(): Verifier  $verifier
     */
    public function register(string $type, Verifier|Closure $verifier): static
    {
        $this->verifiers[$type] = $verifier;
        unset($this->resolved[$type]);

        return $this;
    }

    public function has(string $type): bool
    {
        return isset($this->verifiers[$type]);
    }

    public function get(string $type): Verifier
    {
        if (! $this->has($type)) {
            throw new InvalidArgumentException("Unknown verifier type [{$type}].");
        }

        if (! isset($this->resolved[$type])) {
            $verifier = $this->verifiers[$type];
            $this->resolved[$type] = $verifier instanceof Closure ? $verifier() : $verifier;
        }

        return $this->resolved[$type];
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->verifiers);
    }
}
