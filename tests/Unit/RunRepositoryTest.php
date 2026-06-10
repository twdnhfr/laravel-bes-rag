<?php

use Twdnhfr\BesRag\Data\BesConfig;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\GoalNode;
use Twdnhfr\BesRag\Data\GoalTree;
use Twdnhfr\BesRag\Data\Operation;
use Twdnhfr\BesRag\Data\StepType;
use Twdnhfr\BesRag\Data\TrailStep;
use Twdnhfr\BesRag\Engine\RunRepository;

it('round-trips a goal tree through the database', function () {
    $repository = new RunRepository;
    $run = $repository->createRun('test question?', new BesConfig);

    $root = new GoalNode('g1', 'root goal', suggestedQueries: ['query one']);
    $root->addChild(new GoalNode('g1.1', 'child goal', dependsOn: ['g1']));

    $repository->saveGoalTree($run, new GoalTree([$root]));

    $loaded = $repository->loadGoalTree($run);

    expect($loaded->roots)->toHaveCount(1)
        ->and($loaded->roots[0]->id)->toBe('g1')
        ->and($loaded->roots[0]->children)->toHaveCount(1)
        ->and($loaded->roots[0]->children[0]->id)->toBe('g1.1')
        ->and($loaded->roots[0]->children[0]->dependsOn)->toBe(['g1'])
        ->and($loaded->node('g1')->suggestedQueries)->toBe(['query one']);
});

it('appends new goal nodes without duplicating existing ones', function () {
    $repository = new RunRepository;
    $run = $repository->createRun('test question?', new BesConfig);

    $root = new GoalNode('g1', 'root goal');
    $tree = new GoalTree([$root]);

    $repository->saveGoalTree($run, $tree);
    $root->addChild(new GoalNode('g1.1', 'late child'));
    $repository->saveGoalTree($run, $tree);

    expect($run->goalNodes()->count())->toBe(2);
});

it('round-trips an evidence trail with steps, chunks and scores', function () {
    $repository = new RunRepository;
    $run = $repository->createRun('test question?', new BesConfig);

    $tree = new GoalTree([new GoalNode('g1', 'goal')]);
    $repository->saveGoalTree($run, $tree);

    $trail = new EvidenceTrail;
    $trail->operation = Operation::Seed;
    $trail->addStep(new TrailStep(StepType::SearchQuery, ['queries' => ['q1']], 'g1'));
    $trail->addStep(new TrailStep(StepType::EvidenceSelection, ['chunks' => [[
        'document_id' => 'doc1',
        'chunk_id' => 'c1',
        'text' => 'evidence text',
        'metadata' => ['page' => 3],
        'score' => 0.7,
    ]]], 'g1'));
    $trail->answerDraft = 'draft answer';
    $trail->terminal = true;
    $trail->rawScore = 0.6;
    $trail->backwardScore = 0.8;
    $trail->effectiveScore = 0.65;
    $trail->rawComponents = ['grounded_answer' => 0.9];
    $trail->goalScores = ['g1' => 0.8];
    $trail->goalScoreReasons = ['g1' => ['max_similarity' => 0.81]];

    $repository->saveTrail($run, $trail, $tree);

    expect($trail->id)->not->toBeNull();

    $loaded = $repository->loadTrails($run);

    expect($loaded)->toHaveCount(1);

    $reloaded = $loaded[0];

    expect($reloaded->steps)->toHaveCount(2)
        ->and($reloaded->queries())->toBe(['q1'])
        ->and($reloaded->selectedEvidence()[0]->key())->toBe('doc1/c1')
        ->and($reloaded->answerDraft)->toBe('draft answer')
        ->and($reloaded->terminal)->toBeTrue()
        ->and($reloaded->rawComponents['grounded_answer'])->toEqualWithDelta(0.9, 1e-9)
        ->and($reloaded->goalScores['g1'])->toEqualWithDelta(0.8, 1e-9);

    // Evidence chunks are denormalized for querying/auditing.
    expect($run->candidates()->first()->evidenceChunks()->count())->toBe(1);
});

it('updates scores of an existing trail in place', function () {
    $repository = new RunRepository;
    $run = $repository->createRun('test question?', new BesConfig);
    $tree = new GoalTree([new GoalNode('g1', 'goal')]);
    $repository->saveGoalTree($run, $tree);

    $trail = new EvidenceTrail;
    $repository->saveTrail($run, $trail, $tree);

    $trail->effectiveScore = 0.42;
    $repository->saveTrail($run, $trail, $tree);

    expect($run->candidates()->count())->toBe(1)
        ->and((float) $run->candidates()->first()->effective_score)->toEqualWithDelta(0.42, 1e-9);
});
