<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class IssueImage extends Model
{
    protected $appends = ['url'];

    protected $fillable = [
        'issue_id',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn () => Storage::disk('public')->url($this->path));
    }
}
