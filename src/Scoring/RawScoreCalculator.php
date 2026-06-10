<?php

namespace Twdnhfr\BesRag\Scoring;

use Twdnhfr\BesRag\Data\BesConfig;
use Twdnhfr\BesRag\Data\EvidenceTrail;

/**
 * The hard signal: how good is the trail as an *answer*, independent of
 * goal coverage. LLM-judged components (grounded, citation support,
 * contradictions) come from one memoized judge call; evidence quality and
 * source diversity are computed locally.
 */
class RawScoreCalculator
{
    public function __construct(protected LlmJudge $judge) {}

    public function score(string $question, EvidenceTrail $trail, BesConfig $config): float
    {
        $assessment = $this->judge->assess($question, $trail);

        $components = [
            'grounded_answer' => $assessment['grounded'],
            'citation_support' => $assessment['citation_support'],
            'evidence_quality' => $this->evidenceQuality($trail),
            'contradiction_absence' => $assessment['contradiction_absence'],
            'source_diversity' => $this->sourceDiversity($trail),
        ];

        $trail->rawComponents = $components;

        $score = 0.0;

        foreach ($config->rawScoreWeights as $component => $weight) {
            $score += $weight * ($components[$component] ?? 0.0);
        }

        return max(0.0, min(1.0, $score));
    }

    protected function evidenceQuality(EvidenceTrail $trail): float
    {
        $evidence = $trail->selectedEvidence();

        if ($evidence === []) {
            return 0.0;
        }

        $scores = array_map(fn ($chunk) => max(0.0, min(1.0, $chunk->score)), $evidence);

        return array_sum($scores) / count($scores);
    }

    protected function sourceDiversity(EvidenceTrail $trail): float
    {
        $documents = [];

        foreach ($trail->selectedEvidence() as $chunk) {
            $documents[$chunk->documentId] = true;
        }

        // Three or more distinct sources count as fully diverse.
        return min(1.0, count($documents) / 3);
    }
}
