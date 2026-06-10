<?php

namespace Twdnhfr\BesRag\Decomposition;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Twdnhfr\BesRag\Contracts\GoalDecomposer;
use Twdnhfr\BesRag\Contracts\Llm;
use Twdnhfr\BesRag\Data\GoalNode;
use Twdnhfr\BesRag\Data\GoalTree;
use Twdnhfr\BesRag\Scoring\VerifierRegistry;

/**
 * Backward decomposition via LLM structured output: the question (or a
 * still-unsatisfied goal) is broken into atomic, checkable sub-goals with
 * declarative verifiers. No LLM-generated code is ever executed.
 */
class LlmGoalDecomposer implements GoalDecomposer
{
    public function __construct(
        protected Llm $llm,
        protected VerifierRegistry $verifiers,
        protected ?string $model = null,
    ) {}

    public function decompose(string $question, ?GoalNode $target = null): GoalTree
    {
        $instructions = $this->instructions();

        $prompt = $target === null
            ? "Decompose this question into 3-7 atomic, independently checkable sub-goals:\n\n{$question}"
            : "The root question is:\n\n{$question}\n\n".
              'This sub-goal is still unsatisfied and needs to be decomposed into 2-4 smaller, '.
              "more concrete sub-goals:\n\n\"{$target->description}\"\n\n".
              'Use ids prefixed with "'.$target->id.'." for the new sub-goals.';

        $result = $this->llm->structured($instructions, $prompt, $this->schema(...), $this->model);

        $tree = new GoalTree;
        $parentLevel = $target !== null ? $target->level : -1;

        foreach ((array) ($result['goals'] ?? []) as $index => $goal) {
            $node = $this->toNode((array) $goal, $index, $target);
            $node->level = $parentLevel + 1;

            if ($target !== null) {
                $target->addChild($node);
            }

            $tree->addRoot($node);
        }

        return $tree;
    }

    protected function toNode(array $goal, int $index, ?GoalNode $target): GoalNode
    {
        $verifierType = (string) ($goal['verifier_type'] ?? 'semantic_query_coverage');

        if (! $this->verifiers->has($verifierType)) {
            $verifierType = 'semantic_query_coverage';
        }

        $prefix = $target !== null ? $target->id.'.' : 'g';
        $id = (string) ($goal['id'] ?? $prefix.($index + 1));

        // Sub-goal ids must live under the target's namespace — rewrite
        // anything else so refinement can never collide with existing keys.
        if ($target !== null && ! str_starts_with($id, $prefix)) {
            $id = $prefix.($index + 1);
        }

        return new GoalNode(
            id: $id,
            description: (string) ($goal['description'] ?? ''),
            dependsOn: array_values(array_map('strval', (array) ($goal['depends_on'] ?? []))),
            evidenceRequired: array_values(array_map('strval', (array) ($goal['evidence_required'] ?? []))),
            suggestedQueries: array_values(array_map('strval', (array) ($goal['suggested_queries'] ?? []))),
            verifierType: $verifierType,
        );
    }

    /**
     * @return array<string, Type>
     */
    protected function schema(JsonSchema $schema): array
    {
        return [
            'goals' => $schema->array()->items(
                $schema->object([
                    'id' => $schema->string()->description('Short goal id, e.g. "g1"')->required(),
                    'description' => $schema->string()->description('What must be established, phrased checkably')->required(),
                    'depends_on' => $schema->array()->items($schema->string())->description('Ids of goals that must be satisfied first'),
                    'evidence_required' => $schema->array()->items($schema->string())->description('Kinds of sources that would satisfy this goal'),
                    'suggested_queries' => $schema->array()->items($schema->string())->description('2-3 search queries likely to surface that evidence'),
                    'verifier_type' => $schema->string()->enum($this->verifiers->types())->description('How satisfaction of this goal is checked'),
                ]),
            )->required(),
        ];
    }

    protected function instructions(): string
    {
        return <<<'PROMPT'
        You decompose research questions into atomic, checkable sub-goals for a
        retrieval system. Rules:
        - Each sub-goal must be answerable by retrieving documents; never require reasoning the retriever cannot verify.
        - Make goals atomic: one fact or relation per goal.
        - For multi-hop questions, chain goals with depends_on so later goals build on earlier answers.
        - Suggest concrete search queries per goal.
        - Respond with the structured goal list only.
        PROMPT;
    }
}
