<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueNarration extends Model
{
    protected $fillable = [
        'issue_id',
        'provider',
        'locale',
        'voice_name',
        'source_hash',
        'status',
        'audio_paths',
        'error_message',
        'requested_at',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'audio_paths' => 'array',
            'requested_at' => 'datetime',
            'generated_at' => 'datetime',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class)->withoutGlobalScope('user_owned');
    }
}
