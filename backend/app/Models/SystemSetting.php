<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stored override of a setting declared in config/settings.php.
 *
 * @property int $id
 * @property string $key
 * @property mixed $value
 */
class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
