<?php

namespace Twdnhfr\BesRag\Scoring\Verifiers;

use Twdnhfr\BesRag\Contracts\Verifier;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\GoalNode;
use Twdnhfr\BesRag\Data\VerificationScore;

/**
 * type: evidence_presence
 *
 * Satisfied when the trail holds at least N evidence chunks (params:
 * min_chunks, default 1) attributed to this goal, each carrying source
 * metadata.
 */
class EvidencePresenceVerifier implements Verifier
{
    public function verify(GoalNode $goal, EvidenceTrail $trail): VerificationScore
    {
        $minChunks = max(1, (int) ($goal->verifierParams['min_chunks'] ?? 1));

        $chunks = [];

        foreach ($trail->stepsForGoal($goal->id) as $step) {
            foreach ($step->chunks() as $chunk) {
                $chunks[$chunk->key()] = $chunk;
            }
        }

        // Fall back to the whole trail when no steps are goal-attributed
        // (e.g. after combine operations that merged trails).
        if ($chunks === []) {
            foreach ($trail->selectedEvidence() as $chunk) {
                $chunks[$chunk->key()] = $chunk;
            }
        }

        $count = count($chunks);

        return new VerificationScore(min(1.0, $count / $minChunks), [
            'chunks' => $count,
            'min_chunks' => $minChunks,
        ]);
    }
}
