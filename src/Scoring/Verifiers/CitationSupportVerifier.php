<?php

namespace Twdnhfr\BesRag\Scoring\Verifiers;

use Twdnhfr\BesRag\Contracts\Verifier;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\GoalNode;
use Twdnhfr\BesRag\Data\VerificationScore;
use Twdnhfr\BesRag\Scoring\LlmJudge;

/**
 * type: citation_support
 *
 * LLM-judged: are the trail's answer claims supported by the cited chunks?
 * Shares the memoized judge with the raw score, so this costs no extra
 * LLM call when the trail was already judged.
 */
class CitationSupportVerifier implements Verifier
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

        return new VerificationScore($assessment['citation_support'], [
            'notes' => $assessment['notes'],
        ]);
    }
}
