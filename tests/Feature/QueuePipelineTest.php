<?php

use Twdnhfr\BesRag\Contracts\Embedder;
use Twdnhfr\BesRag\Contracts\Llm;
use Twdnhfr\BesRag\Contracts\Retriever;
use Twdnhfr\BesRag\Data\BesConfig;
use Twdnhfr\BesRag\Engine\EngineFactory;
use Twdnhfr\BesRag\Facades\BesRag;
use Twdnhfr\BesRag\Jobs\StartRun;
use Twdnhfr\BesRag\Models\Run;
use Twdnhfr\BesRag\Testing\FakeEmbedder;
use Twdnhfr\BesRag\Tests\Fixtures\TeslaFixture;

beforeEach(function () {
    // The queue pipeline rebuilds the engine per job from container
    // bindings — exactly what a consuming app has to provide.
    $this->app->instance(Llm::class, TeslaFixture::llm());
    $this->app->instance(Embedder::class, new FakeEmbedder);
    $this->app->instance(Retriever::class, TeslaFixture::retriever());

    config()->set('bes-rag', array_replace_recursive(
        config('bes-rag'),
        TeslaFixture::configOverrides(),
    ));
});

it('runs the full pipeline through queued jobs', function () {
    // The sync connection executes each dispatched job inline, so the
    // whole chain (decompose → seed → step* → finalize) runs to the end.
    $result = BesRag::make()->dispatch(TeslaFixture::QUESTION);

    expect($result->status())->toBe(Run::STATUS_COMPLETED)
        ->and($result->answer())->toContain('Eberhard')
        ->and($result->run()->candidates()->count())->toBeGreaterThanOrEqual(2);
});

it('marks the run failed when a job cannot build its engine', function () {
    $this->app->offsetUnset(Retriever::class);

    $run = Run::create([
        'question' => 'anything?',
        'status' => Run::STATUS_PENDING,
        'budget' => 5,
        'config_json' => (new BesConfig)->toArray(),
    ]);

    try {
        (new StartRun($run->id))->handle(app(EngineFactory::class));
    } catch (RuntimeException $exception) {
        (new StartRun($run->id))->failed($exception);
    }

    expect($run->refresh()->status)->toBe(Run::STATUS_FAILED)
        ->and($run->error)->toContain('No retriever available');
});

it('skips work for runs that already finished', function () {
    $run = Run::create([
        'question' => 'done already?',
        'status' => Run::STATUS_COMPLETED,
        'budget' => 5,
        'config_json' => (new BesConfig)->toArray(),
    ]);

    (new StartRun($run->id))->handle(app(EngineFactory::class));

    expect($run->refresh()->status)->toBe(Run::STATUS_COMPLETED)
        ->and($run->goalNodes()->count())->toBe(0);
});
