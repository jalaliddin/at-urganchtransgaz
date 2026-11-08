<?php

namespace App\Services\Software;

use App\Exceptions\DomainConflictException;
use App\Models\AgentToken;
use App\Models\Asset;
use App\Models\DeviceAgent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Agents bound to one device. Tokens are random, stored only as SHA-256 hashes and returned in
 * plain text once; issuing a new token revokes the previous ones.
 */
class AgentService
{
    public const PREFIX = 'atg_';

    /** @return array{0: DeviceAgent, 1: string} the agent and its plain token */
    public function register(Asset $asset, string $name, ?string $platform, User $by): array
    {
        return DB::transaction(function () use ($asset, $name, $platform, $by): array {
            if (DeviceAgent::query()->where('asset_id', $asset->id)->lockForUpdate()->exists()) {
                throw new DomainConflictException(__('messages.agent_exists'));
            }
            $agent = DeviceAgent::create(['asset_id' => $asset->id, 'name' => $name, 'platform' => $platform, 'created_by' => $by->id]);

            return [$agent, $this->issue($agent, $by)];
        });
    }

    /** New token for the agent; earlier tokens stop working. */
    public function issue(DeviceAgent $agent, User $by): string
    {
        return DB::transaction(function () use ($agent, $by): string {
            $now = CarbonImmutable::now();
            AgentToken::query()->where('agent_id', $agent->id)->whereNull('revoked_at')->update(['revoked_at' => $now, 'revoked_by' => $by->id]);
            $plain = self::PREFIX.Str::random(40);
            AgentToken::create(['agent_id' => $agent->id, 'token_hash' => hash('sha256', $plain), 'prefix' => substr($plain, 0, 12), 'created_by' => $by->id]);
            $agent->forceFill(['is_active' => true])->save();

            return $plain;
        });
    }

    public function revoke(DeviceAgent $agent, User $by): DeviceAgent
    {
        DB::transaction(function () use ($agent, $by): void {
            AgentToken::query()->where('agent_id', $agent->id)->whereNull('revoked_at')
                ->update(['revoked_at' => CarbonImmutable::now(), 'revoked_by' => $by->id]);
            $agent->forceFill(['is_active' => false])->save();
        });

        return $agent;
    }

    /** The active agent behind a bearer token, or null. */
    public function authenticate(string $plain): ?AgentToken
    {
        if (! str_starts_with($plain, self::PREFIX)) {
            return null;
        }
        $token = AgentToken::query()->with('agent')->where('token_hash', hash('sha256', $plain))->whereNull('revoked_at')->first();

        return $token !== null && $token->agent->is_active ? $token : null;
    }
}
