<?php

namespace Twdnhfr\BesRag\Scoring\Verifiers;

use Twdnhfr\BesRag\Contracts\Verifier;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\GoalNode;
use Twdnhfr\BesRag\Data\VerificationScore;
use Twdnhfr\BesRag\Scoring\LlmJudge;

/**
 * type: contradiction_check
 *
 * LLM-judged: no other evidence chunk strongly contradicts the answer.
 */
class ContradictionVerifier implements Verifier
{
    public function __construct(protected LlmJudge $judge, protected string $question = '') {}

    public function withQuestion(string $question): static
    {
        $clone = clone $this;
        $clone->question = $question;

        return $clone;
    }

    public function verify(GoalNode $goal, EvidenceTrail $trail): VerificationScore
    {
        $assessment = $this->judge->assess($this->question !== '' ? $this->question : $goal->description, $trail);

        return new VerificationScore($assessment['contradiction_absence'], [
            'notes' => $assessment['notes'],
        ]);
    }
}
