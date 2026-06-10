<?php

use Twdnhfr\BesRag\Data\BesConfig;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\GoalTree;
use Twdnhfr\BesRag\Data\Operation;
use Twdnhfr\BesRag\Data\StepType;
use Twdnhfr\BesRag\Data\TrailStep;
use Twdnhfr\BesRag\Engine\SearchRun;
use Twdnhfr\BesRag\Models\Run;
use Twdnhfr\BesRag\Operators\CombineOperator;
use Twdnhfr\BesRag\Operators\CrossoverOperator;
use Twdnhfr\BesRag\Operators\DeleteOperator;
use Twdnhfr\BesRag\Operators\TranslocateOperator;

function chunkContent(string $doc, string $chunk, string $text = 'text'): array
{
    return ['chunks' => [[
        'document_id' => $doc,
        'chunk_id' => $chunk,
        'text' => $text,
        'metadata' => [],
        'score' => 0.5,
    ]]];
}

function trailWithGoals(array $goalChunks): EvidenceTrail
{
    $trail = new EvidenceTrail;
    $trail->id = random_int(1, 100000);

    foreach ($goalChunks as $goalId => $chunks) {
        $trail->addStep(new TrailStep(StepType::SearchQuery, ['queries' => ["query {$goalId}"]], $goalId));

        foreach ($chunks as [$doc, $chunk]) {
            $trail->addStep(new TrailStep(StepType::EvidenceSelection, chunkContent($doc, $chunk), $goalId));
        }
    }

    return $trail;
}

function searchRun(): SearchRun
{
    return new SearchRun(new Run, new BesConfig, new GoalTree);
}

it('combines two trails and deduplicates shared evidence', function () {
    $a = trailWithGoals(['g1' => [['doc1', 'c1']]]);
    $b = trailWithGoals(['g2' => [['doc1', 'c1'], ['doc2', 'c1']]]);

    $child = (new CombineOperator)->apply(searchRun(), $a, $b);

    expect($child)->not->toBeNull()
        ->and($child->operation)->toBe(Operation::Combine)
        ->and($child->parentIds)->toBe([$a->id, $b->id]);

    $keys = array_map(fn ($chunk) => $chunk->key(), $child->selectedEvidence());

    expect($keys)->toBe(['doc1/c1', 'doc2/c1']);
});

it('returns null when combining adds nothing new', function () {
    $a = trailWithGoals(['g1' => [['doc1', 'c1']]]);
    $b = trailWithGoals(['g1' => [['doc1', 'c1']]]);

    // B's only chunk is already in A — and its query steps carry no chunks.
    $b->steps = array_values(array_filter($b->steps, fn ($step) => $step->type === StepType::EvidenceSelection));

    expect((new CombineOperator)->apply(searchRun(), $a, $b))->toBeNull();
});

it('deletes the phase of the weakest-scoring goal', function () {
    $trail = trailWithGoals([
        'g1' => [['doc1', 'c1']],
        'g2' => [['doc2', 'c1']],
    ]);
    $trail->goalScores = ['g1' => 0.9, 'g2' => 0.1];

    $child = (new DeleteOperator)->apply(searchRun(), $trail);

    expect($child)->not->toBeNull()
        ->and($child->touchedGoalIds())->toBe(['g1']);
});

it('refuses to delete the only phase', function () {
    $trail = trailWithGoals(['g1' => [['doc1', 'c1']]]);
    $trail->goalScores = ['g1' => 0.1];

    expect((new DeleteOperator)->apply(searchRun(), $trail))->toBeNull();
});

it('translocates the better evidence for a shared goal', function () {
    $a = trailWithGoals([
        'g1' => [['weak', 'c1']],
        'g2' => [['doc2', 'c1']],
    ]);
    $a->goalScores = ['g1' => 0.2, 'g2' => 0.8];

    $b = trailWithGoals(['g1' => [['strong', 'c1']]]);
    $b->goalScores = ['g1' => 0.9];

    $child = (new TranslocateOperator)->apply(searchRun(), $a, $b);

    expect($child)->not->toBeNull();

    $keys = array_map(fn ($chunk) => $chunk->key(), $child->selectedEvidence());

    expect($keys)->toContain('strong/c1')
        ->and($keys)->toContain('doc2/c1')
        ->and($keys)->not->toContain('weak/c1');
});

it('returns null when the second trail has no better goal phase', function () {
    $a = trailWithGoals(['g1' => [['doc1', 'c1']]]);
    $a->goalScores = ['g1' => 0.9];

    $b = trailWithGoals(['g1' => [['doc2', 'c1']]]);
    $b->goalScores = ['g1' => 0.1];

    expect((new TranslocateOperator)->apply(searchRun(), $a, $b))->toBeNull();
});

it('crosses over along goal boundaries, not step indexes', function () {
    $a = trailWithGoals([
        'g1' => [['a1', 'c1']],
        'g2' => [['a2', 'c1']],
    ]);

    $b = trailWithGoals([
        'g2' => [['b2', 'c1']],
        'g3' => [['b3', 'c1']],
    ]);

    $child = (new CrossoverOperator)->apply(searchRun(), $a, $b);

    expect($child)->not->toBeNull();

    // Prefix: A's first half (g1). Suffix: B's goals beyond the prefix (g2, g3 from B).
    $keys = array_map(fn ($chunk) => $chunk->key(), $child->selectedEvidence());

    expect($keys)->toContain('a1/c1')
        ->and($keys)->toContain('b2/c1')
        ->and($keys)->toContain('b3/c1')
        ->and($keys)->not->toContain('a2/c1');
});

it('returns null when crossover degenerates to a copy of A', function () {
    $a = trailWithGoals(['g1' => [['a1', 'c1']]]);
    $b = trailWithGoals(['g1' => [['b1', 'c1']]]);

    expect((new CrossoverOperator)->apply(searchRun(), $a, $b))->toBeNull();
});
