<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Fills created_by / updated_by from the authenticated user (if any).
 * Uses Auth::hasUser() so it never triggers user resolution itself.
 */
trait HasUserStamps
{
    public static function bootHasUserStamps(): void
    {
        static::creating(function ($model): void {
            if (Auth::hasUser()) {
                $model->created_by ??= Auth::id();
                $model->updated_by = Auth::id();
            }
        });

        static::updating(function ($model): void {
            if (Auth::hasUser()) {
                $model->updated_by = Auth::id();
            }
        });
    }
}
