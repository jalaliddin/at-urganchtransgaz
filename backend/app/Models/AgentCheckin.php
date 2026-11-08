<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $agent_id
 * @property string|null $ip
 * @property string $status
 * @property int $software_count
 * @property string|null $message
 * @property CarbonImmutable $received_at
 */
class AgentCheckin extends Model
{
    public $timestamps = false;

    protected $fillable = ['agent_id', 'ip', 'status', 'software_count', 'message', 'received_at'];

    protected function casts(): array
    {
        return ['received_at' => 'immutable_datetime', 'software_count' => 'integer'];
    }
}
