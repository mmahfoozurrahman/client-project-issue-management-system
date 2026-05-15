<?php

namespace App\Models;

use App\Models\Concerns\UserOwned;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use UserOwned;

    protected $fillable = [
        'name',
        'description',
        'client_id',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class)->withoutGlobalScope('user_owned');
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::withoutGlobalScope('user_owned')
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }

    public function issueTags(): HasMany
    {
        return $this->hasMany(IssueTag::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function projectMembers(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }
}
