<?php

namespace App\Models;

use App\Models\Concerns\UserOwned;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        return $this->hasMany(Issue::class);
    }

    public function issueTags(): HasMany
    {
        return $this->hasMany(IssueTag::class);
    }
}
