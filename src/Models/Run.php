<?php

namespace Twdnhfr\BesRag\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Twdnhfr\BesRag\Database\Factories\RunFactory;

/**
 * @property int $id
 * @property string $question
 * @property string $status
 * @property int $budget
 * @property int $used_budget
 * @property int $llm_calls
 * @property int $steps_without_progress
 * @property float $best_score
 * @property array<string, mixed> $config_json
 * @property int|null $final_candidate_id
 * @property string|null $answer
 * @property string|null $error
 */
class Run extends Model
{
    /** @use HasFactory<RunFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DECOMPOSING = 'decomposing';

    public const STATUS_SEARCHING = 'searching';

    public const STATUS_FINALIZING = 'finalizing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'bes_runs';

    protected $guarded = [];

    protected $casts = [
        'config_json' => 'array',
        'budget' => 'integer',
        'used_budget' => 'integer',
        'llm_calls' => 'integer',
        'steps_without_progress' => 'integer',
        'best_score' => 'float',
    ];

    /** @return HasMany<GoalNode, $this> */
    public function goalNodes(): HasMany
    {
        return $this->hasMany(GoalNode::class, 'run_id');
    }

    /** @return HasMany<Candidate, $this> */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class, 'run_id');
    }

    /** @return BelongsTo<Candidate, $this> */
    public function finalCandidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'final_candidate_id');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    protected static function newFactory(): RunFactory
    {
        return RunFactory::new();
    }
}
