<?php

namespace Twdnhfr\BesRag\Scoring\Verifiers;

use Twdnhfr\BesRag\Contracts\Embedder;
use Twdnhfr\BesRag\Contracts\Verifier;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\GoalNode;
use Twdnhfr\BesRag\Data\VerificationScore;
use Twdnhfr\BesRag\Support\Vectors;

/**
 * type: semantic_query_coverage
 *
 * The goal counts as covered when at least one of the trail's queries or
 * selected evidence chunks is semantically close to the goal description.
 * Below the threshold, partial credit is given proportionally, which keeps
 * the backward score dense (the BES requirement) instead of binary.
 */
class SemanticCoverageVerifier implements Verifier
{
    public function __construct(
        protected Embedder $embedder,
        protected float $defaultThreshold = 0.72,
    ) {}

    public function verify(GoalNode $goal, EvidenceTrail $trail): VerificationScore
    {
        $sources = array_merge(
            $trail->queries(),
            array_map(fn ($chunk) => $chunk->text, $trail->selectedEvidence()),
        );

        $sources = array_values(array_filter($sources, fn (string $text) => trim($text) !== ''));

        if ($sources === []) {
            return new VerificationScore(0.0, ['reason' => 'no queries or evidence in trail']);
        }

        $threshold = (float) ($goal->verifierParams['threshold'] ?? $this->defaultThreshold);
        $goalVector = $this->embedder->embedOne($goal->description);
        $sourceVectors = $this->embedder->embed($sources);

        $maxSimilarity = 0.0;
        $bestSource = null;

        foreach ($sourceVectors as $index => $vector) {
            $similarity = Vectors::cosine($goalVector, $vector);

            if ($similarity > $maxSimilarity) {
                $maxSimilarity = $similarity;
                $bestSource = $sources[$index];
            }
        }

        $score = $threshold > 0 ? min(1.0, $maxSimilarity / $threshold) : 0.0;

        return new VerificationScore($score, [
            'max_similarity' => round($maxSimilarity, 4),
            'threshold' => $threshold,
            'best_source' => $bestSource !== null ? mb_substr($bestSource, 0, 200) : null,
        ]);
    }
}
