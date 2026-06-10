<?php

namespace Twdnhfr\BesRag\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $candidate_id
 * @property string $type
 * @property array<string, mixed> $content_json
 * @property string|null $goal_key
 * @property int $position
 */
class CandidateStep extends Model
{
    protected $table = 'bes_candidate_steps';

    protected $guarded = [];

    protected $casts = [
        'content_json' => 'array',
        'position' => 'integer',
    ];

    /** @return BelongsTo<Candidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'candidate_id');
    }
}
