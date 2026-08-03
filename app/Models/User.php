<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password', 'is_admin', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function pinnedIssues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class, 'issue_pins')->withTimestamps();
    }

    public function memberProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function hasOwnProjects(): bool
    {
        return Project::withoutGlobalScope('user_owned')
            ->where('user_id', $this->id)
            ->exists();
    }

    public function accessibleProjectIds(): array
    {
        if ($this->accessibleProjectIdsCache !== null) {
            return $this->accessibleProjectIdsCache;
        }

        if ($this->is_admin) {
            return $this->accessibleProjectIdsCache = Project::withoutGlobalScope('user_owned')
                ->pluck('id')
                ->all();
        }

        $ownedIds = Project::withoutGlobalScope('user_owned')
            ->where('user_id', $this->id)
            ->pluck('id')
            ->all();

        $memberIds = ProjectMember::where('user_id', $this->id)
            ->pluck('project_id')
            ->all();

        return $this->accessibleProjectIdsCache = array_values(
            array_unique(array_merge($ownedIds, $memberIds))
        );
    }

    public function projectRoleOn(int $projectId): ?string
    {
        $member = ProjectMember::where('project_id', $projectId)
            ->where('user_id', $this->id)
            ->with('role')
            ->first();

        return $member?->role?->slug;
    }

    public function canOnProject(string $permission, int $projectId): bool
    {
        if ($this->is_admin) {
            return true;
        }

        $cacheKey = $projectId . ':' . $permission;

        if (array_key_exists($cacheKey, $this->projectPermissionCache)) {
            return $this->projectPermissionCache[$cacheKey];
        }

        $member = ProjectMember::where('project_id', $projectId)
            ->where('user_id', $this->id)
            ->with('role.permissions')
            ->first();

        if (!$member) {
            return $this->projectPermissionCache[$cacheKey] = false;
        }

        $hasPermission = $member->role->permissions->contains('slug', $permission);

        return $this->projectPermissionCache[$cacheKey] = $hasPermission;
    }

    protected array $projectPermissionCache = [];
    protected ?array $accessibleProjectIdsCache = null;
    protected ?array $manageableProjectIdsCache = null;

    protected $appends = ['avatar_url'];

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar_path) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($this->avatar_path);
    }


    public function canAccessProjectsPage(): bool
    {
        // অ্যাডমিন বা যে নিজে প্রজেক্ট তৈরি করেছে তারা সবসময় এক্সেস পাবে
        if ($this->is_admin || $this->hasOwnProjects()) {
            return true;
        }

        // ProjectMember মডেল ব্যবহার করে সরাসরি চেক করা হচ্ছে যেন Global Scope-এ বাধা না পায়
        return ProjectMember::where('user_id', $this->id)
            ->whereHas('role.permissions', function ($q) {
                // আপনি যে slug-টি ডাটাবেসে সেভ করেছেন সেটি এখানে দিন। যেমন: 'projects.view_list' বা 'project.list'
                $q->where('slug', 'project.list');
            })
            ->exists();

    }

    public function canAccessClientsPage(): bool
    {
        // অ্যাডমিন বা যে নিজে প্রজেক্ট তৈরি করেছে তারা সবসময় এক্সেস পাবে
        if ($this->is_admin || $this->hasOwnProjects()) {
            return true;
        }

        // ProjectMember মডেল ব্যবহার করে সরাসরি চেক করা হচ্ছে যেন Global Scope-এ বাধা না পায়
        return ProjectMember::where('user_id', $this->id)
            ->whereHas('role.permissions', function ($q) {
                // আপনি যে slug-টি ডাটাবেসে সেভ করেছেন সেটি এখানে দিন। যেমন: 'projects.view_list' বা 'project.list'
                $q->where('slug', 'client.list');
            })
            ->exists();

    }

    public function manageableProjectIds(): array
    {
        if ($this->manageableProjectIdsCache !== null) {
            return $this->manageableProjectIdsCache;
        }

        if ($this->is_admin) {
            return $this->manageableProjectIdsCache = Project::withoutGlobalScope('user_owned')
                ->pluck('id')
                ->all();
        }

        return $this->manageableProjectIdsCache = ProjectMember::where('user_id', $this->id)
            ->whereHas('role', function ($query) {
                $query->whereIn('slug', ['owner', 'developer']);
            })
            ->pluck('project_id')
            ->all();
    }

    public function canAccessTagsPage(): bool
    {
        return $this->is_admin || $this->manageableProjectIds() !== [];
    }

    public function hasPermission(string $permission): bool
    {

        if ($this->is_admin) {
            return true;
        }

        return ProjectMember::where('user_id', $this->id)
            ->whereHas('role.permissions', function ($q) use ($permission) {
                $q->where('slug', $permission);
            })
            ->exists();
    }
}
