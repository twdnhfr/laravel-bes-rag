<?php

namespace Twdnhfr\BesRag\Contracts;

use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\GoalNode;
use Twdnhfr\BesRag\Data\VerificationScore;

/**
 * Declarative goal verifier. BES-RAG deliberately does NOT evaluate
 * LLM-generated code (the original BES inference code uses Python eval);
 * verifiers are registered PHP classes selected by type string.
 */
interface Verifier
{
    public function verify(GoalNode $goal, EvidenceTrail $trail): VerificationScore;
}
