<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonNarration extends Model
{
    protected $fillable = [
        'lesson_id',
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

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
