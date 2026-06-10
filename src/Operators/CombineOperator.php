<?php

namespace Twdnhfr\BesRag\Operators;

use Twdnhfr\BesRag\Contracts\EvolutionOperator;
use Twdnhfr\BesRag\Data\EvidenceTrail;
use Twdnhfr\BesRag\Data\Operation;
use Twdnhfr\BesRag\Data\StepType;
use Twdnhfr\BesRag\Data\TrailStep;
use Twdnhfr\BesRag\Engine\SearchRun;

/**
 * Merge two trails that answer different sub-goals: the child keeps all of
 * A and adopts B's steps, with evidence deduplicated by document/chunk id.
 */
class CombineOperator implements EvolutionOperator
{
    public function name(): string
    {
        return 'combine';
    }

    public function arity(): int
    {
        return 2;
    }

    public function apply(SearchRun $run, EvidenceTrail ...$parents): ?EvidenceTrail
    {
        [$a, $b] = $parents;

        $child = $a->childCopy(Operation::Combine);
        $child->parentIds = array_values(array_filter([$a->id, $b->id], fn ($id) => $id !== null));

        $known = [];

        foreach ([...$child->chunks(), ...$child->selectedEvidence()] as $chunk) {
            $known[$chunk->key()] = true;
        }

        $added = false;

        foreach ($b->steps as $step) {
            if ($step->type === StepType::Answer) {
                continue; // The combined trail gets rescored; stale answers don't carry over.
            }

            $content = $step->content;

            if (isset($content['chunks'])) {
                $content['chunks'] = array_values(array_filter(
                    $content['chunks'],
                    function (array $chunk) use (&$known) {
                        $key = ($chunk['document_id'] ?? '').'/'.($chunk['chunk_id'] ?? '');

                        if (isset($known[$key])) {
                            return false;
                        }

                        $known[$key] = true;

                        return true;
                    },
                ));

                if ($content['chunks'] === []) {
                    continue;
                }
            }

            $child->addStep(new TrailStep($step->type, $content, $step->goalId));
            $added = true;
        }

        if (! $added) {
            return null; // B contributed nothing new.
        }

        // Merged evidence invalidates any previous draft.
        $child->answerDraft = null;
        $child->terminal = false;
        $child->steps = array_values(array_filter(
            $child->steps,
            fn (TrailStep $step) => $step->type !== StepType::Answer,
        ));

        return $child;
    }
}
