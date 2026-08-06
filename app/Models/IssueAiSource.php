<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueAiSource extends Model
{
    protected $fillable = [
        'issue_id',
        'source_tool',
        'model',
        'source_url',
        'external_source_id',
        'repository',
        'git_branch',
        'commit_hash',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
