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
}
