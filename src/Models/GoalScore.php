<?php

namespace Twdnhfr\BesRag\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $candidate_id
 * @property int $goal_node_id
 * @property string $goal_key
 * @property float $score
 * @property array<string, mixed> $reason_json
 */
class GoalScore extends Model
{
    protected $table = 'bes_goal_scores';

    protected $guarded = [];

    protected $casts = [
        'reason_json' => 'array',
        'score' => 'float',
    ];

    /** @return BelongsTo<Candidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'candidate_id');
    }
}
