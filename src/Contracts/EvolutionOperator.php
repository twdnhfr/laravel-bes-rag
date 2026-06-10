<?php

namespace Twdnhfr\BesRag\Contracts;

use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Engine\SearchRun;

interface EvolutionOperator
{
    /**
     * Operator key as used in the configured operator mix
     * (expand, combine, delete, translocate, crossover, ...).
     */
    public function name(): string;

    /**
     * Number of parent trails the operator consumes (1 or 2).
     */
    public function arity(): int;

    /**
     * Produce a child trail from the given parents, or null when the
     * operator is not applicable (e.g. nothing to delete).
     */
    public function apply(SearchRun $run, EvidenceTrail ...$parents): ?EvidenceTrail;
}
