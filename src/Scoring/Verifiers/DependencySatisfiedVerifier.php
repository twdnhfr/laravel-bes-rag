<?php

namespace Twdnhfr\BesRag\Scoring\Verifiers;

use Twdnhfr\BesRag\Contracts\Verifier;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\GoalNode;
use Twdnhfr\BesRag\Data\VerificationScore;

/**
 * type: dependency_satisfied
 *
 * Gating verifier: scores the minimum of the goal's dependencies' scores,
 * so a goal is only "checkable" once everything it depends on is covered.
 */
class DependencySatisfiedVerifier implements Verifier
{
    public function verify(GoalNode $goal, EvidenceTrail $trail): VerificationScore
    {
        if ($goal->dependsOn === []) {
            return new VerificationScore(1.0, ['reason' => 'no dependencies']);
        }

        $scores = [];

        foreach ($goal->dependsOn as $dependencyId) {
            $scores[$dependencyId] = $trail->goalScores[$dependencyId] ?? 0.0;
        }

        return new VerificationScore(min($scores), ['dependencies' => $scores]);
    }
}
