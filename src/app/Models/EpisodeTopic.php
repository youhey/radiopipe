<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Episode に含まれる topic processing snapshot。
 */
#[Fillable([
    'episode_id',
    'topic_id',
    'upstream_provider',
    'upstream_id',
    'source_name',
    'source_type',
    'title',
    'url',
    'screening_status',
    'editorial_status',
    'scenario_selection_status',
    'sort_order',
    'topic_draft_json',
    'screening_json',
    'editorial_json',
    'scenario_selection_json',
    'metadata',
])]
class EpisodeTopic extends Model
{
    /**
     * 所属する Episode。
     *
     * @return BelongsTo<Episode, $this>
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'topic_draft_json' => 'array',
            'screening_json' => 'array',
            'editorial_json' => 'array',
            'scenario_selection_json' => 'array',
            'metadata' => 'array',
        ];
    }
}
