<?php

namespace Twdnhfr\BesRag\Engine;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Twdnhfr\BesRag\Contracts\CandidateGenerator;
use Twdnhfr\BesRag\Contracts\Llm;
use Twdnhfr\BesRag\Contracts\Reranker;
use Twdnhfr\BesRag\Contracts\Retriever;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\GoalNode;
use Twdnhfr\BesRag\Data\Operation;
use Twdnhfr\BesRag\Data\RetrievalQuery;
use Twdnhfr\BesRag\Data\RetrievedChunk;
use Twdnhfr\BesRag\Data\StepType;
use Twdnhfr\BesRag\Data\TrailStep;

/**
 * Forward search: seeds fresh trails from distinct query strategies and
 * expands existing trails towards their next open sub-goal (queries →
 * retrieval → evidence selection → synthesis note → optional answer draft).
 */
class TrailExpander implements CandidateGenerator
{
    public function __construct(
        protected Retriever $retriever,
        protected Llm $llm,
        protected AnswerSynthesizer $synthesizer,
        protected ?Reranker $reranker = null,
        protected ?string $model = null,
    ) {}

    public function seed(SearchRun $run, int $seedIndex): EvidenceTrail
    {
        $trail = new EvidenceTrail;
        $trail->operation = Operation::Seed;

        $queries = $this->seedQueries($run, $seedIndex);
        $goal = $run->goalTree->openGoals($trail)[0] ?? null;

        $this->executeSearch($run, $trail, $queries, $goal);

        return $trail;
    }

    public function expand(SearchRun $run, EvidenceTrail $parent): EvidenceTrail
    {
        $child = $parent->childCopy(Operation::Expand);
        $goal = $run->goalTree->openGoals($child)[0] ?? null;

        if ($goal === null) {
            // All goals covered — produce/refresh the terminal answer draft.
            $child->answerDraft = $this->synthesizer->synthesize($run->question(), $child);
            $child->terminal = true;
            $child->addStep(new TrailStep(StepType::Answer, ['answer' => $child->answerDraft]));

            return $child;
        }

        $generated = $this->generateQueries($run, $child, $goal);

        $this->executeSearch($run, $child, $generated['queries'], $goal, $generated['note']);

        // Draft an answer once the frontier is empty after this expansion.
        if ($run->goalTree->openGoals($child) === []) {
            $child->answerDraft = $this->synthesizer->synthesize($run->question(), $child);
            $child->terminal = true;
            $child->addStep(new TrailStep(StepType::Answer, ['answer' => $child->answerDraft]));
        }

        return $child;
    }

    /**
     * Distinct query strategies per seed: the raw question first, then the
     * suggested queries of the goals in rotation. No LLM call needed.
     *
     * @return list<string>
     */
    protected function seedQueries(SearchRun $run, int $seedIndex): array
    {
        if ($seedIndex === 0) {
            return [$run->question()];
        }

        $suggestions = [];

        foreach ($run->goalTree->leaves() as $goal) {
            foreach ($goal->suggestedQueries as $query) {
                $suggestions[] = $query;
            }

            if ($goal->suggestedQueries === []) {
                $suggestions[] = $goal->description;
            }
        }

        if ($suggestions === []) {
            return [$run->question()];
        }

        // Rotate through suggestions so every seed starts differently.
        $offset = ($seedIndex - 1) % count($suggestions);

        return array_slice(array_merge($suggestions, $suggestions), $offset, min(2, count($suggestions)));
    }

    /**
     * @return array{queries: list<string>, note: string}
     */
    protected function generateQueries(SearchRun $run, EvidenceTrail $trail, GoalNode $goal): array
    {
        $context = [];

        foreach (array_slice($trail->selectedEvidence(), -5) as $chunk) {
            $context[] = '- '.mb_substr($chunk->text, 0, 200);
        }

        $result = $this->llm->structured(
            'You generate search queries for a retrieval system working through sub-goals of a research question. '
            .'Generate 1-3 short, distinct queries for the given sub-goal. Use facts from the evidence so far to make '
            .'queries concrete (e.g. resolve "the company" to its actual name). Add a one-sentence note on your strategy.',
            "Root question: {$run->question()}\n\n"
            ."Current sub-goal: {$goal->description}\n"
            .($goal->suggestedQueries !== [] ? 'Suggested starting points: '.implode(' | ', $goal->suggestedQueries)."\n" : '')
            .($context !== [] ? "\nEvidence so far:\n".implode("\n", $context) : ''),
            fn (JsonSchema $schema) => [
                'queries' => $schema->array()->items($schema->string())->required(),
                'note' => $schema->string()->required(),
            ],
            $this->model,
        );

        $queries = array_values(array_filter(array_map('strval', (array) ($result['queries'] ?? []))));

        if ($queries === []) {
            $queries = $goal->suggestedQueries !== [] ? array_slice($goal->suggestedQueries, 0, 2) : [$goal->description];
        }

        return [
            'queries' => array_slice($queries, 0, 3),
            'note' => (string) ($result['note'] ?? ''),
        ];
    }

    /**
     * @param  list<string>  $queries
     */
    protected function executeSearch(
        SearchRun $run,
        EvidenceTrail $trail,
        array $queries,
        ?GoalNode $goal,
        string $note = '',
    ): void {
        $goalId = $goal?->id;

        $trail->addStep(new TrailStep(StepType::SearchQuery, ['queries' => $queries], $goalId));

        $known = [];

        foreach ([...$trail->chunks(), ...$trail->selectedEvidence()] as $chunk) {
            $known[$chunk->key()] = true;
        }

        $retrieved = [];

        foreach ($queries as $query) {
            $chunks = $this->retriever->retrieve(
                new RetrievalQuery($query, $run->config->retrievalContext, $goalId),
                $run->config->topK,
            );

            if ($this->reranker !== null && $chunks !== []) {
                $chunks = $this->reranker->rerank($query, $chunks, $run->config->topK);
            }

            foreach ($chunks as $chunk) {
                $retrieved[$chunk->key()] = $chunk;
            }
        }

        $fresh = array_values(array_filter(
            $retrieved,
            fn (RetrievedChunk $chunk) => ! isset($known[$chunk->key()]),
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($fresh !== []) {
            $trail->addStep(new TrailStep(StepType::RetrievedChunks, [
                'chunks' => array_map(fn (RetrievedChunk $chunk) => $chunk->toArray(), $fresh),
            ], $goalId));

            // Keep the strongest new chunks as evidence for this goal.
            usort($fresh, fn (RetrievedChunk $a, RetrievedChunk $b) => $b->score <=> $a->score);
            $selected = array_slice($fresh, 0, 3);

            $trail->addStep(new TrailStep(StepType::EvidenceSelection, [
                'chunks' => array_map(fn (RetrievedChunk $chunk) => $chunk->toArray(), $selected),
            ], $goalId));
        }

        if ($note !== '') {
            $trail->addStep(new TrailStep(StepType::SynthesisNote, ['note' => $note], $goalId));
        }
    }
}
