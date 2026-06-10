<?php

namespace Twdnhfr\BesRag\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Twdnhfr\BesRag\BesRagManager;
use Twdnhfr\BesRag\Engine\RunRepository;
use Twdnhfr\BesRag\Models\Run;

class DeepAnswerController
{
    public function __construct(
        protected BesRagManager $manager,
        protected RunRepository $repository,
    ) {}

    /**
     * POST /deep-answer — start a deep search. Queued by default; pass
     * "sync": true to wait for the answer in-request (dev/small corpora).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:2000'],
            'sync' => ['sometimes', 'boolean'],
            'budget' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        $search = $this->manager->make();

        if (isset($validated['budget'])) {
            $search->budget($validated['budget']);
        }

        $result = ($validated['sync'] ?? false)
            ? $search->answer($validated['question'])
            : $search->dispatch($validated['question']);

        return new JsonResponse($this->payload($result->run()), 201);
    }

    /**
     * GET /runs/{run} — status and (when finished) the cited answer.
     */
    public function show(Run $run): JsonResponse
    {
        return new JsonResponse($this->payload($run));
    }

    /**
     * GET /runs/{run}/debug — goal tree, all candidates and their scores;
     * the audit view of the whole search.
     */
    public function debug(Run $run): JsonResponse
    {
        $tree = $this->repository->loadGoalTree($run);

        $candidates = $run->candidates()
            ->orderByDesc('effective_score')
            ->get()
            ->map(fn ($candidate) => [
                'id' => $candidate->id,
                'operation' => $candidate->operation,
                'parent_ids' => $candidate->parent_ids_json,
                'terminal' => $candidate->terminal,
                'raw_score' => $candidate->raw_score,
                'backward_score' => $candidate->backward_score,
                'effective_score' => $candidate->effective_score,
                'raw_components' => $candidate->raw_components_json,
                'answer_text' => $candidate->answer_text,
                'goal_scores' => $candidate->goalScores()->get()
                    ->mapWithKeys(fn ($score) => [$score->goal_key => [
                        'score' => $score->score,
                        'reason' => $score->reason_json,
                    ]])->all(),
                'steps' => $candidate->steps->map(fn ($step) => [
                    'position' => $step->position,
                    'type' => $step->type,
                    'goal_key' => $step->goal_key,
                    'content' => $step->content_json,
                ])->all(),
            ]);

        return new JsonResponse([
            'run' => $this->payload($run),
            'goal_tree' => $tree->toArray(),
            'candidates' => $candidates,
        ]);
    }

    /**
     * GET /runs/{run}/stream — Server-Sent Events with run progress until
     * the run finishes (or the timeout elapses).
     */
    public function stream(Run $run): StreamedResponse
    {
        return new StreamedResponse(function () use ($run) {
            $deadline = time() + 120;
            $lastPayload = null;

            while (time() < $deadline) {
                $run->refresh();
                $payload = json_encode($this->payload($run));

                if ($payload !== $lastPayload) {
                    echo "event: progress\n";
                    echo "data: {$payload}\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();

                    $lastPayload = $payload;
                }

                if ($run->isFinished()) {
                    echo "event: done\n";
                    echo "data: {$payload}\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();

                    return;
                }

                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Run $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'question' => $run->question,
            'budget' => $run->budget,
            'used_budget' => $run->used_budget,
            'llm_calls' => $run->llm_calls,
            'best_score' => $run->best_score,
            'answer' => $run->answer,
            'error' => $run->error,
        ];
    }
}
