<?php

use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\GoalNode;
use Twdnhfr\BesRag\Data\GoalTree;
use Twdnhfr\BesRag\Data\StepType;
use Twdnhfr\BesRag\Data\TrailStep;
use Twdnhfr\BesRag\Scoring\Verifiers\DependencySatisfiedVerifier;
use Twdnhfr\BesRag\Scoring\Verifiers\EntityMatchVerifier;
use Twdnhfr\BesRag\Scoring\Verifiers\EvidencePresenceVerifier;
use Twdnhfr\BesRag\Scoring\Verifiers\SemanticCoverageVerifier;
use Twdnhfr\BesRag\Testing\FakeEmbedder;

function evidenceTrail(array $texts, ?string $goalId = null): EvidenceTrail
{
    $trail = new EvidenceTrail;

    $chunks = [];

    foreach ($texts as $i => $text) {
        $chunks[] = [
            'document_id' => 'doc'.$i,
            'chunk_id' => 'c1',
            'text' => $text,
            'metadata' => ['source' => 'test'],
            'score' => 0.8,
        ];
    }

    $trail->addStep(new TrailStep(StepType::EvidenceSelection, ['chunks' => $chunks], $goalId));

    return $trail;
}

it('verifies semantic coverage against queries and evidence', function () {
    $verifier = new SemanticCoverageVerifier(new FakeEmbedder, defaultThreshold: 0.5);

    $goal = new GoalNode('g1', 'identify the company that produces the Model S');

    $covered = evidenceTrail(['Tesla produces the Model S electric sedan in Fremont']);
    $uncovered = evidenceTrail(['bananas are yellow and tasty fruit snacks']);

    expect($verifier->verify($goal, $covered)->score)
        ->toBeGreaterThan($verifier->verify($goal, $uncovered)->score);
});

it('scores empty trails as zero coverage', function () {
    $verifier = new SemanticCoverageVerifier(new FakeEmbedder);

    expect($verifier->verify(new GoalNode('g1', 'anything'), new EvidenceTrail)->score)->toBe(0.0);
});

it('requires the configured number of evidence chunks', function () {
    $verifier = new EvidencePresenceVerifier;

    $goal = new GoalNode('g1', 'goal', verifierParams: ['min_chunks' => 2]);

    expect($verifier->verify($goal, evidenceTrail(['one chunk'], 'g1'))->score)->toEqualWithDelta(0.5, 1e-9)
        ->and($verifier->verify($goal, evidenceTrail(['one', 'two'], 'g1'))->score)->toEqualWithDelta(1.0, 1e-9);
});

it('matches required entities in the evidence', function () {
    $verifier = new EntityMatchVerifier;

    $goal = new GoalNode('g1', 'goal', verifierParams: ['entities' => ['Eberhard', 'Tarpenning']]);

    $trail = evidenceTrail(['Tesla was founded by Martin Eberhard.']);

    $result = $verifier->verify($goal, $trail);

    expect($result->score)->toEqualWithDelta(0.5, 1e-9)
        ->and($result->reason['matched'])->toBe(['Eberhard']);
});

it('gates on dependency scores', function () {
    $verifier = new DependencySatisfiedVerifier;

    $goal = new GoalNode('g2', 'depends on g1', dependsOn: ['g1']);

    $trail = new EvidenceTrail;
    $trail->goalScores = ['g1' => 0.4];

    expect($verifier->verify($goal, $trail)->score)->toEqualWithDelta(0.4, 1e-9);
});

it('keeps dependent goals out of the open frontier until satisfied', function () {
    $g1 = new GoalNode('g1', 'first hop');
    $g2 = new GoalNode('g2', 'second hop', dependsOn: ['g1']);

    $tree = new GoalTree([$g1, $g2]);

    $trail = new EvidenceTrail;
    $trail->goalScores = ['g1' => 0.2, 'g2' => 0.0];

    expect(array_map(fn ($g) => $g->id, $tree->openGoals($trail)))->toBe(['g1']);

    $trail->goalScores['g1'] = 1.0;

    expect(array_map(fn ($g) => $g->id, $tree->openGoals($trail)))->toBe(['g2']);
});
