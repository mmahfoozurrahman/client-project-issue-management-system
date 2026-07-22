<?php

namespace App\Models;

use App\Models\Concerns\UserOwned;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        return $this->belongsTo(Project::class)->withoutGlobalScope('user_owned');
    }

    public function parentIssue(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withoutGlobalScope('user_owned');
    }

    public function subIssues(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->withoutGlobalScope('user_owned');
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::withoutGlobalScope('user_owned')
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
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

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(IssueTag::class, 'issue_issue_tag');
    }

    public function pinnedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'issue_pins')->withTimestamps();
    }
}
