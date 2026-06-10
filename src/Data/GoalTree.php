<?php

namespace Twdnhfr\BesRag\Data;

class GoalTree
{
    /**
     * @param  list<GoalNode>  $roots
     */
    public function __construct(public array $roots = []) {}

    public function addRoot(GoalNode $node): static
    {
        $this->roots[] = $node;

        return $this;
    }

    /**
     * Flat list of every node in the tree (depth-first).
     *
     * @return list<GoalNode>
     */
    public function allNodes(): array
    {
        $nodes = [];

        $walk = function (GoalNode $node) use (&$nodes, &$walk): void {
            $nodes[] = $node;
            foreach ($node->children as $child) {
                $walk($child);
            }
        };

        foreach ($this->roots as $root) {
            $walk($root);
        }

        return $nodes;
    }

    public function node(string $id): ?GoalNode
    {
        foreach ($this->allNodes() as $node) {
            if ($node->id === $id) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @return list<GoalNode>
     */
    public function leaves(): array
    {
        return array_values(array_filter($this->allNodes(), fn (GoalNode $node) => $node->isLeaf()));
    }

    public function maxLevel(): int
    {
        $max = 0;

        foreach ($this->allNodes() as $node) {
            $max = max($max, $node->level);
        }

        return $max;
    }

    /**
     * Whether the goal's dependencies are satisfied for the given trail.
     */
    public function dependenciesSatisfied(GoalNode $goal, EvidenceTrail $trail, float $threshold = 0.999): bool
    {
        foreach ($goal->dependsOn as $dependencyId) {
            if (($trail->goalScores[$dependencyId] ?? 0.0) < $threshold) {
                return false;
            }
        }

        return true;
    }

    /**
     * Goals that are not yet satisfied by the trail and whose dependencies
     * are met — the working frontier for forward expansion.
     *
     * @return list<GoalNode>
     */
    public function openGoals(EvidenceTrail $trail, float $threshold = 0.999): array
    {
        return array_values(array_filter(
            $this->leaves(),
            fn (GoalNode $goal) => ($trail->goalScores[$goal->id] ?? 0.0) < $threshold
                && $this->dependenciesSatisfied($goal, $trail, $threshold),
        ));
    }

    /**
     * Unsatisfied leaves across the best trail — decomposition targets for
     * periodic backward expansion.
     *
     * @return list<GoalNode>
     */
    public function unsatisfiedLeaves(EvidenceTrail $trail, float $threshold = 0.999): array
    {
        return array_values(array_filter(
            $this->leaves(),
            fn (GoalNode $goal) => ($trail->goalScores[$goal->id] ?? 0.0) < $threshold,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(fn (GoalNode $root) => $root->toArray(), $this->roots);
    }

    /**
     * @param  list<array<string, mixed>>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(array_map(fn (array $root) => GoalNode::fromArray($root), $data));
    }
}
