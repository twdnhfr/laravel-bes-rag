<?php

use Twdnhfr\BesRag\Contracts\Embedder;
use Twdnhfr\BesRag\Contracts\Llm;
use Twdnhfr\BesRag\Contracts\Retriever;
use Twdnhfr\BesRag\Testing\FakeEmbedder;
use Twdnhfr\BesRag\Tests\Fixtures\TeslaFixture;

beforeEach(function () {
    $this->app->instance(Llm::class, TeslaFixture::llm());
    $this->app->instance(Embedder::class, new FakeEmbedder);
    $this->app->instance(Retriever::class, TeslaFixture::retriever());

    config()->set('bes-rag', array_replace_recursive(
        config('bes-rag'),
        TeslaFixture::configOverrides(),
    ));
});

it('answers synchronously over http', function () {
    $response = $this->postJson('/bes-rag/deep-answer', [
        'question' => TeslaFixture::QUESTION,
        'sync' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'completed');

    expect($response->json('answer'))->toContain('Eberhard');
});

it('queues by default and exposes run status', function () {
    $response = $this->postJson('/bes-rag/deep-answer', [
        'question' => TeslaFixture::QUESTION,
    ]);

    $response->assertCreated();

    $id = $response->json('id');

    // sync queue connection → pipeline already done by the time we poll.
    $this->getJson("/bes-rag/runs/{$id}")
        ->assertOk()
        ->assertJsonPath('status', 'completed');
});

it('exposes the full search trace on the debug endpoint', function () {
    $id = $this->postJson('/bes-rag/deep-answer', [
        'question' => TeslaFixture::QUESTION,
        'sync' => true,
    ])->json('id');

    $response = $this->getJson("/bes-rag/runs/{$id}/debug");

    $response->assertOk();

    expect($response->json('goal_tree'))->not->toBeEmpty()
        ->and($response->json('candidates'))->not->toBeEmpty()
        ->and($response->json('candidates.0'))->toHaveKeys(['operation', 'effective_score', 'goal_scores', 'steps']);
});

it('validates the question', function () {
    $this->postJson('/bes-rag/deep-answer', ['question' => ''])
        ->assertUnprocessable();
});
