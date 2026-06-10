<?php

namespace Twdnhfr\BesRag\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $candidate_id
 * @property int|null $step_id
 * @property string $document_id
 * @property string $chunk_id
 * @property string $text
 * @property array<string, mixed> $metadata_json
 * @property float $score
 */
class EvidenceChunk extends Model
{
    protected $table = 'bes_evidence_chunks';

    protected $guarded = [];

    protected $casts = [
        'metadata_json' => 'array',
        'score' => 'float',
    ];

    /** @return BelongsTo<Candidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'candidate_id');
    }
}
