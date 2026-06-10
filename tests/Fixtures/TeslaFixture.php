<?php

namespace Twdnhfr\BesRag\Tests\Fixtures;

use Twdnhfr\BesRag\Data\RetrievedChunk;
use Twdnhfr\BesRag\Retrieval\ArrayRetriever;
use Twdnhfr\BesRag\Testing\FakeLlm;

/**
 * Shared multi-hop scenario: "Who founded the company that produces the
 * Model S?" — hop 1 resolves the company (Tesla), hop 2 its founders.
 */
class TeslaFixture
{
    public const QUESTION = 'Who founded the company that produces the Model S?';

    public static function retriever(): ArrayRetriever
    {
        return new ArrayRetriever([
            new RetrievedChunk('models', 'c1', 'The Model S is a battery electric sedan produced by Tesla.', ['source' => 'wiki']),
            new RetrievedChunk('founders', 'c1', 'Tesla was founded in 2003 by Martin Eberhard and Marc Tarpenning.', ['source' => 'wiki']),
            new RetrievedChunk('noise', 'c1', 'Bananas are yellow fruit rich in potassium.', ['source' => 'wiki']),
        ]);
    }

    public static function llm(): FakeLlm
    {
        $llm = new FakeLlm;

        $llm->onStructured(function (string $instructions, string $prompt): array {
            if (str_contains($instructions, 'decompose research questions')) {
                return [
                    'goals' => [
                        [
                            'id' => 'g1',
                            'description' => 'identify the company that produces the Model S sedan',
                            'depends_on' => [],
                            'evidence_required' => [],
                            'suggested_queries' => ['Model S produced by company'],
                            'verifier_type' => 'semantic_query_coverage',
                        ],
                        [
                            'id' => 'g2',
                            'description' => 'identify who founded Tesla',
                            'depends_on' => ['g1'],
                            'evidence_required' => [],
                            'suggested_queries' => ['Tesla founded by'],
                            'verifier_type' => 'evidence_presence',
                        ],
                    ],
                ];
            }

            if (str_contains($prompt, 'Current sub-goal:')) {
                $query = str_contains($prompt, 'founded') ? 'Tesla founded by Eberhard' : 'Model S produced by Tesla';

                return ['queries' => [$query], 'note' => 'targeting the open sub-goal'];
            }

            if (str_contains($instructions, 'evidence auditor')) {
                $grounded = str_contains($prompt, 'Eberhard') ? 0.9 : 0.2;

                return [
                    'grounded' => $grounded,
                    'citation_support' => $grounded,
                    'contradiction_absence' => 1.0,
                    'notes' => 'fixture judgement',
                ];
            }

            return [];
        });

        $llm->onText(fn (string $instructions, string $prompt): string => 'Tesla, which produces the Model S [models/c1], was founded by Martin Eberhard and Marc Tarpenning [founders/c1].');

        return $llm;
    }

    /**
     * @return array<string, mixed>
     */
    public static function configOverrides(): array
    {
        return [
            'budget' => 8,
            'seed_candidates' => 2,
            'no_progress_limit' => 4,
            'max_llm_calls' => 60,
            'thresholds' => [
                'semantic_coverage' => 0.30,
                'grounded_answer' => 0.80,
                'citation_support' => 0.80,
            ],
        ];
    }
}
