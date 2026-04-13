<?php

namespace App\Models;

use App\Models\Concerns\UserOwned;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Issue extends Model
{
    use UserOwned;

    protected $fillable = [
        'title',
        'description',
        'status',
        'done_at',
        'project_id',
        'user_id',
        'parent_id',
    ];

    protected $casts = [
        'done_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parentIssue(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function subIssues(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(IssueImage::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(IssueFile::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(IssueLink::class);
    }
}
