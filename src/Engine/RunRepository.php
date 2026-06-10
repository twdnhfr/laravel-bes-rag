<?php

namespace Twdnhfr\BesRag\Engine;

use Twdnhfr\BesRag\Data\BesConfig;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\GoalNode as GoalNodeData;
use Twdnhfr\BesRag\Data\GoalTree;
use Twdnhfr\BesRag\Data\Operation;
use Twdnhfr\BesRag\Data\TrailStep;
use Twdnhfr\BesRag\Models\Candidate;
use Twdnhfr\BesRag\Models\CandidateStep;
use Twdnhfr\BesRag\Models\GoalNode;
use Twdnhfr\BesRag\Models\Run;

/**
 * Bridges the runtime DTOs (GoalTree, EvidenceTrail) and their Eloquent
 * persistence. Everything the engine produces is written here so queued
 * jobs and the debug API see the same state.
 */
class RunRepository
{
    public function createRun(string $question, BesConfig $config): Run
    {
        return Run::create([
            'question' => $question,
            'status' => Run::STATUS_PENDING,
            'budget' => $config->budget,
            'config_json' => $config->toArray(),
        ]);
    }

    /**
     * Persist every goal node that does not have a database id yet.
     * Existing nodes are left untouched, so periodic re-decomposition can
     * append children without rewriting the tree.
     */
    public function saveGoalTree(Run $run, GoalTree $tree): void
    {
        $persist = function (GoalNodeData $node, ?int $parentModelId) use (&$persist, $run): void {
            if ($node->modelId === null) {
                $model = GoalNode::create([
                    'run_id' => $run->id,
                    'parent_id' => $parentModelId,
                    'goal_key' => $node->id,
                    'level' => $node->level,
                    'description' => $node->description,
                    'depends_on_json' => $node->dependsOn,
                    'evidence_required_json' => $node->evidenceRequired,
                    'suggested_queries_json' => $node->suggestedQueries,
                    'verifier_type' => $node->verifierType,
                    'verifier_params_json' => $node->verifierParams,
                ]);

                $node->modelId = $model->id;
            }

            foreach ($node->children as $child) {
                $persist($child, $node->modelId);
            }
        };

        foreach ($tree->roots as $root) {
            $persist($root, null);
        }
    }

    public function loadGoalTree(Run $run): GoalTree
    {
        $models = $run->goalNodes()->orderBy('id')->get();

        /** @var array<int, GoalNodeData> $byModelId */
        $byModelId = [];
        $tree = new GoalTree;

        foreach ($models as $model) {
            $byModelId[$model->id] = $model->toData();
        }

        foreach ($models as $model) {
            $node = $byModelId[$model->id];

            if ($model->parent_id !== null && isset($byModelId[$model->parent_id])) {
                $parent = $byModelId[$model->parent_id];
                $node->parentId = $parent->id;
                $parent->children[] = $node;
            } else {
                $tree->addRoot($node);
            }
        }

        return $tree;
    }

    /**
     * Insert a new trail or update the scores of an existing one.
     */
    public function saveTrail(Run $run, EvidenceTrail $trail, GoalTree $tree): Candidate
    {
        if ($trail->id !== null) {
            $candidate = Candidate::query()->findOrFail($trail->id);
            $candidate->update([
                'raw_score' => $trail->rawScore,
                'backward_score' => $trail->backwardScore,
                'effective_score' => $trail->effectiveScore,
                'raw_components_json' => $trail->rawComponents,
                'terminal' => $trail->terminal,
                'answer_text' => $trail->answerDraft,
            ]);

            $this->syncGoalScores($candidate, $trail, $tree);

            return $candidate;
        }

        $candidate = Candidate::create([
            'run_id' => $run->id,
            'parent_id' => $trail->parentIds[0] ?? null,
            'parent_ids_json' => $trail->parentIds,
            'operation' => $trail->operation->value,
            'terminal' => $trail->terminal,
            'raw_score' => $trail->rawScore,
            'backward_score' => $trail->backwardScore,
            'effective_score' => $trail->effectiveScore,
            'raw_components_json' => $trail->rawComponents,
            'answer_text' => $trail->answerDraft,
        ]);

        foreach ($trail->steps as $position => $step) {
            $stepModel = CandidateStep::create([
                'candidate_id' => $candidate->id,
                'type' => $step->type->value,
                'content_json' => $step->content,
                'goal_key' => $step->goalId,
                'position' => $position,
            ]);

            foreach ($step->chunks() as $chunk) {
                $candidate->evidenceChunks()->create([
                    'step_id' => $stepModel->id,
                    'document_id' => $chunk->documentId,
                    'chunk_id' => $chunk->chunkId,
                    'text' => $chunk->text,
                    'metadata_json' => $chunk->metadata,
                    'score' => $chunk->score,
                ]);
            }
        }

        $trail->id = $candidate->id;

        $this->syncGoalScores($candidate, $trail, $tree);

        return $candidate;
    }

    /**
     * @return list<EvidenceTrail>
     */
    public function loadTrails(Run $run): array
    {
        $candidates = $run->candidates()
            ->where('active', true)
            ->with('steps')
            ->orderBy('id')
            ->get();

        $trails = [];

        foreach ($candidates as $candidate) {
            $trails[] = $this->toTrail($candidate);
        }

        return $trails;
    }

    public function toTrail(Candidate $candidate): EvidenceTrail
    {
        $trail = new EvidenceTrail;
        $trail->id = $candidate->id;
        $trail->operation = Operation::from($candidate->operation);
        $trail->parentIds = $candidate->parent_ids_json ?? [];
        $trail->terminal = $candidate->terminal;
        $trail->answerDraft = $candidate->answer_text;
        $trail->rawScore = $candidate->raw_score;
        $trail->backwardScore = $candidate->backward_score;
        $trail->effectiveScore = $candidate->effective_score;
        $trail->rawComponents = $candidate->raw_components_json ?? [];

        foreach ($candidate->steps as $step) {
            $trail->addStep(TrailStep::fromArray([
                'type' => $step->type,
                'content' => $step->content_json,
                'goal_id' => $step->goal_key,
            ]));
        }

        foreach ($candidate->goalScores()->get() as $score) {
            $trail->goalScores[$score->goal_key] = $score->score;
            $trail->goalScoreReasons[$score->goal_key] = $score->reason_json ?? [];
        }

        return $trail;
    }

    protected function syncGoalScores(Candidate $candidate, EvidenceTrail $trail, GoalTree $tree): void
    {
        foreach ($trail->goalScores as $goalKey => $score) {
            $node = $tree->node($goalKey);

            if ($node === null || $node->modelId === null) {
                continue;
            }

            $candidate->goalScores()->updateOrCreate(
                ['goal_node_id' => $node->modelId],
                [
                    'goal_key' => $goalKey,
                    'score' => $score,
                    'reason_json' => $trail->goalScoreReasons[$goalKey] ?? [],
                ],
            );
        }
    }
}
