<?php

namespace Twdnhfr\BesRag\Data;

class GoalNode
{
    /** @var list<GoalNode> */
    public array $children = [];

    /** Database id of the bes_goal_nodes row once persisted. */
    public ?int $modelId = null;

    /**
     * @param  list<string>  $dependsOn
     * @param  list<string>  $evidenceRequired
     * @param  list<string>  $suggestedQueries
     * @param  array<string, mixed>  $verifierParams
     */
    public function __construct(
        public string $id,
        public string $description,
        public array $dependsOn = [],
        public array $evidenceRequired = [],
        public array $suggestedQueries = [],
        public string $verifierType = 'semantic_query_coverage',
        public array $verifierParams = [],
        public ?string $parentId = null,
        public int $level = 0,
    ) {}

    public function isLeaf(): bool
    {
        return $this->children === [];
    }

    public function addChild(GoalNode $child): static
    {
        $child->parentId = $this->id;
        $child->level = $this->level + 1;
        $this->children[] = $child;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'depends_on' => $this->dependsOn,
            'evidence_required' => $this->evidenceRequired,
            'suggested_queries' => $this->suggestedQueries,
            'verifier' => array_merge(['type' => $this->verifierType], $this->verifierParams),
            'parent_id' => $this->parentId,
            'level' => $this->level,
            'children' => array_map(fn (GoalNode $child) => $child->toArray(), $this->children),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $verifier = (array) ($data['verifier'] ?? []);
        $verifierType = (string) ($verifier['type'] ?? 'semantic_query_coverage');
        unset($verifier['type']);

        $node = new self(
            id: (string) $data['id'],
            description: (string) ($data['description'] ?? ''),
            dependsOn: array_values((array) ($data['depends_on'] ?? [])),
            evidenceRequired: array_values((array) ($data['evidence_required'] ?? [])),
            suggestedQueries: array_values((array) ($data['suggested_queries'] ?? [])),
            verifierType: $verifierType,
            verifierParams: $verifier,
            parentId: $data['parent_id'] ?? null,
            level: (int) ($data['level'] ?? 0),
        );

        foreach ((array) ($data['children'] ?? []) as $child) {
            $childNode = self::fromArray($child);
            $childNode->parentId = $node->id;
            $childNode->level = $node->level + 1;
            $node->children[] = $childNode;
        }

        return $node;
    }
}
