<?php

namespace Twdnhfr\BesRag\Operators;

use Twdnhfr\BesRag\Contracts\CandidateGenerator;
use Twdnhfr\BesRag\Contracts\EvolutionOperator;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Engine\SearchRun;

/**
 * Standard forward expansion: work on the parent's next open sub-goal.
 */
class ExpandOperator implements EvolutionOperator
{
    public function __construct(protected CandidateGenerator $generator) {}

    public function name(): string
    {
        return 'expand';
    }

    public function arity(): int
    {
        return 1;
    }

    public function apply(SearchRun $run, EvidenceTrail ...$parents): ?EvidenceTrail
    {
        return $this->generator->expand($run, $parents[0]);
    }
}
