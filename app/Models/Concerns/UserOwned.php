<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait UserOwned
{
    protected static function bootUserOwned(): void
    {
        static::addGlobalScope('user_owned', function (Builder $builder): void {
            if (app()->runningInConsole() || ! auth()->check()) {
                return;
            }

            $builder->where($builder->qualifyColumn('user_id'), auth()->id());
        });

        static::creating(function ($model): void {
            if (empty($model->user_id) && auth()->check()) {
                $model->user_id = auth()->id();
            }
        });
    }
}
