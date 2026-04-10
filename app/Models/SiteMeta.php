<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SiteMeta extends Model
{
    protected $table = 'site_meta';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function value(string $key, ?string $default = null): ?string
    {
        if (! Schema::hasTable('site_meta')) {
            return $default;
        }

        return static::query()
            ->where('key', $key)
            ->value('value') ?? $default;
    }
}
