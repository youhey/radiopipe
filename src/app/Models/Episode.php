<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 生成済み Episode の永続化モデル。
 */
#[Fillable([
    'episode_key',
    'date',
    'published_at',
    'processed_at',
    'character_profile_id',
    'character_key',
    'status',
    'title',
    'language',
    'target_duration_seconds',
    'estimated_duration_seconds',
    'scenario_json',
    'metadata',
    'errors',
])]
class Episode extends Model
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';

    public const STATUS_FAILED = 'failed';

    /**
     * Episode に含まれる topic snapshot 一覧。
     *
     * @return HasMany<EpisodeTopic, $this>
     */
    public function topics(): HasMany
    {
        return $this->hasMany(EpisodeTopic::class);
    }

    /**
     * 生成時に参照したキャラクター人格。
     *
     * @return BelongsTo<CharacterProfile, $this>
     */
    public function characterProfile(): BelongsTo
    {
        return $this->belongsTo(CharacterProfile::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'published_at' => 'datetime',
            'processed_at' => 'datetime',
            'target_duration_seconds' => 'integer',
            'estimated_duration_seconds' => 'integer',
            'scenario_json' => 'array',
            'metadata' => 'array',
            'errors' => 'array',
        ];
    }
}
