<?php

namespace Twdnhfr\BesRag\Scoring\Verifiers;

use Twdnhfr\BesRag\Contracts\Verifier;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\GoalNode;
use Twdnhfr\BesRag\Data\VerificationScore;

/**
 * type: entity_match
 *
 * Checks that required entities / date values (params: entities, falling
 * back to the goal's evidence_required list) literally appear in the
 * selected evidence.
 */
class EntityMatchVerifier implements Verifier
{
    public function verify(GoalNode $goal, EvidenceTrail $trail): VerificationScore
    {
        $entities = array_values(array_filter(array_map(
            'strval',
            (array) ($goal->verifierParams['entities'] ?? $goal->evidenceRequired),
        )));

        if ($entities === []) {
            return new VerificationScore(0.0, ['reason' => 'no entities to match']);
        }

        $haystack = mb_strtolower(implode("\n", array_map(
            fn ($chunk) => $chunk->text,
            $trail->selectedEvidence(),
        )));

        $matched = [];

        foreach ($entities as $entity) {
            if (str_contains($haystack, mb_strtolower($entity))) {
                $matched[] = $entity;
            }
        }

        return new VerificationScore(count($matched) / count($entities), [
            'matched' => $matched,
            'expected' => $entities,
        ]);
    }
}
