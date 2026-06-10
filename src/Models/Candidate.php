<?php

namespace Twdnhfr\BesRag\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $run_id
 * @property int|null $parent_id
 * @property list<int> $parent_ids_json
 * @property string $operation
 * @property bool $terminal
 * @property bool $active
 * @property float $raw_score
 * @property float $backward_score
 * @property float $effective_score
 * @property array<string, float>|null $raw_components_json
 * @property string|null $answer_text
 */
class Candidate extends Model
{
    protected $table = 'bes_candidates';

    protected $guarded = [];

    protected $casts = [
        'parent_ids_json' => 'array',
        'raw_components_json' => 'array',
        'terminal' => 'boolean',
        'active' => 'boolean',
        'raw_score' => 'float',
        'backward_score' => 'float',
        'effective_score' => 'float',
    ];

    /** @return BelongsTo<Run, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }

    /** @return HasMany<CandidateStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(CandidateStep::class, 'candidate_id')->orderBy('position');
    }

    /** @return HasMany<EvidenceChunk, $this> */
    public function evidenceChunks(): HasMany
    {
        return $this->hasMany(EvidenceChunk::class, 'candidate_id');
    }

    /** @return HasMany<GoalScore, $this> */
    public function goalScores(): HasMany
    {
        return $this->hasMany(GoalScore::class, 'candidate_id');
    }
}
