<?php

namespace App\Models;

use App\Models\Concerns\UserOwned;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

    /**
     * Expose the derived slug and its detail URL on every serialized issue,
     * so links can be built as /issues/{id}/{slug} (the slug is cosmetic).
     */
    protected $appends = ['slug', 'url'];

    protected $casts = [
        'done_at' => 'datetime',
    ];

    /**
     * Slug built from the issue title followed by the names of its attached tags,
     * e.g. "suura-al-kafiruun-dandapani-focus-and-awareness".
     *
     * Tags are only folded in when the relation is already loaded, so serializing
     * large issue collections never triggers extra (N+1) queries. Tag parts are
     * sorted so the generated URL stays stable regardless of database row order.
     */
    public function getSlugAttribute(): string
    {
        $parts = [Str::slug((string) $this->title)];

        if ($this->relationLoaded('tags')) {
            $tagParts = $this->tags
                ->map(fn ($tag) => Str::slug((string) $tag->name))
                ->filter(fn (string $part) => $part !== '')
                ->unique()
                ->sort()
                ->values();

            $parts = [...$parts, ...$tagParts->all()];
        }

        return collect($parts)
            ->filter(fn (string $part) => $part !== '')
            ->unique()
            ->implode('-');
    }

    public function getUrlAttribute(): string
    {
        $slug = $this->slug;

        return $slug !== ''
            ? "/issues/{$this->id}/{$slug}"
            : "/issues/{$this->id}";
    }

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

    public function aiSource(): HasOne
    {
        return $this->hasOne(IssueAiSource::class);
    }

    public function narration(): HasOne
    {
        return $this->hasOne(IssueNarration::class);
    }
}
