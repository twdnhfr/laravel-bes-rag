<?php

namespace Twdnhfr\BesRag\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Twdnhfr\BesRag\Data\GoalNode as GoalNodeData;

/**
 * @property int $id
 * @property int $run_id
 * @property int|null $parent_id
 * @property string $goal_key
 * @property int $level
 * @property string $description
 * @property list<string> $depends_on_json
 * @property list<string> $evidence_required_json
 * @property list<string> $suggested_queries_json
 * @property string $verifier_type
 * @property array<string, mixed> $verifier_params_json
 */
class GoalNode extends Model
{
    protected $table = 'bes_goal_nodes';

    protected $guarded = [];

    protected $casts = [
        'depends_on_json' => 'array',
        'evidence_required_json' => 'array',
        'suggested_queries_json' => 'array',
        'verifier_params_json' => 'array',
        'level' => 'integer',
    ];

    /** @return BelongsTo<Run, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }

    public function toData(): GoalNodeData
    {
        $data = new GoalNodeData(
            id: $this->goal_key,
            description: $this->description,
            dependsOn: $this->depends_on_json ?? [],
            evidenceRequired: $this->evidence_required_json ?? [],
            suggestedQueries: $this->suggested_queries_json ?? [],
            verifierType: $this->verifier_type,
            verifierParams: $this->verifier_params_json ?? [],
            level: $this->level,
        );

        $data->modelId = $this->id;

        return $data;
    }
}
