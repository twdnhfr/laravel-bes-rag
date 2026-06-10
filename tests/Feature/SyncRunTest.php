<?php

use Twdnhfr\BesRag\Contracts\Retriever;
use Twdnhfr\BesRag\Data\RetrievalQuery;
use Twdnhfr\BesRag\Facades\BesRag;
use Twdnhfr\BesRag\Models\Run;
use Twdnhfr\BesRag\Testing\FakeEmbedder;
use Twdnhfr\BesRag\Tests\Fixtures\TeslaFixture;

it('answers a multi-hop question with citations in one process', function () {
    $llm = TeslaFixture::llm();

    $result = BesRag::make()
        ->retriever(TeslaFixture::retriever())
        ->llm($llm)
        ->embedder(new FakeEmbedder)
        ->withConfig(TeslaFixture::configOverrides())
        ->answer(TeslaFixture::QUESTION);

    expect($result->status())->toBe(Run::STATUS_COMPLETED)
        ->and($result->answer())->toContain('Eberhard')
        ->and($result->answer())->toContain('[founders/c1]');

    $run = $result->run();

    // Cost accounting and search trace are persisted.
    expect($run->llm_calls)->toBeGreaterThan(0)
        ->and($run->llm_calls)->toBe($llm->calls())
        ->and($run->candidates()->count())->toBeGreaterThanOrEqual(2)
        ->and($run->goalNodes()->count())->toBeGreaterThanOrEqual(2);

    // The winning trail is auditable: steps, evidence and scores.
    $trail = $result->evidenceTrail();

    expect($trail)->not->toBeNull()
        ->and($trail->steps)->not->toBeEmpty()
        ->and($result->citations())->not->toBeEmpty();

    $scores = $result->scores();

    expect($scores['effective'])->toBeGreaterThan(0)
        ->and($scores['goals'])->toHaveKeys(['g1', 'g2']);
});

it('synthesizes the final answer only from selected evidence', function () {
    $result = BesRag::make()
        ->retriever(TeslaFixture::retriever())
        ->llm(TeslaFixture::llm())
        ->embedder(new FakeEmbedder)
        ->withConfig(TeslaFixture::configOverrides())
        ->answer(TeslaFixture::QUESTION);

    // Every citation in the result resolves to a persisted evidence chunk.
    $run = $result->run();
    $chunkKeys = $run->finalCandidate->evidenceChunks()
        ->get()
        ->map(fn ($chunk) => $chunk->document_id.'/'.$chunk->chunk_id)
        ->all();

    foreach ($result->citations() as $citation) {
        expect($chunkKeys)->toContain($citation['document_id'].'/'.$citation['chunk_id']);
    }
});

it('stops deterministically on the llm call cap', function () {
    $llm = TeslaFixture::llm();

    $result = BesRag::make()
        ->retriever(TeslaFixture::retriever())
        ->llm($llm)
        ->embedder(new FakeEmbedder)
        ->withConfig(array_replace(TeslaFixture::configOverrides(), [
            'max_llm_calls' => 3,
            'thresholds' => ['semantic_coverage' => 0.99, 'grounded_answer' => 0.99, 'citation_support' => 0.99],
        ]))
        ->answer(TeslaFixture::QUESTION);

    // The run still completes (finalize always runs), but the search loop
    // was cut off by the call cap, not the step budget.
    expect($result->status())->toBe(Run::STATUS_COMPLETED)
        ->and($result->run()->used_budget)->toBeLessThan(8);
});

it('delivers the retrieval context to every retriever call', function () {
    $retriever = new class(TeslaFixture::retriever()) implements Retriever
    {
        /** @var list<array<string, mixed>> */
        public array $seen = [];

        public function __construct(private readonly Retriever $inner) {}

        public function retrieve(RetrievalQuery $query, int $topK = 5): array
        {
            $this->seen[] = $query->filters;

            return $this->inner->retrieve($query, $topK);
        }
    };

    $result = BesRag::make()
        ->retriever($retriever)
        ->llm(TeslaFixture::llm())
        ->embedder(new FakeEmbedder)
        ->withConfig(TeslaFixture::configOverrides())
        ->retrievalContext(['brain_id' => 42])
        ->answer(TeslaFixture::QUESTION);

    expect($result->status())->toBe(Run::STATUS_COMPLETED)
        ->and($retriever->seen)->not->toBeEmpty();

    foreach ($retriever->seen as $filters) {
        expect($filters)->toBe(['brain_id' => 42]);
    }

    // The context survives serialization onto the run row (queue pipeline).
    expect($result->run()->config_json['retrieval_context'] ?? null)->toBe(['brain_id' => 42]);
});
