<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $board_id
 * @property string $name
 * @property string $color
 */
class TaskLabel extends Model
{
    protected $fillable = ['board_id', 'name', 'color'];

    /** Reachable only through a board the user can see. */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $model = static::query()->where($field ?? 'id', $value)->firstOrFail();
        $user = request()->user();
        if ($user instanceof User && ! TaskBoard::query()->visibleTo($user)->whereKey($model->getAttribute('board_id'))->exists()) {
            abort(404);
        }

        return $model;
    }
}
