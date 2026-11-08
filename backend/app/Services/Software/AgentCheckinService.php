<?php

namespace App\Services\Software;

use App\Enums\InstallationSource;
use App\Models\AgentCheckin;
use App\Models\AntivirusStatus;
use App\Models\Asset;
use App\Models\DeviceAgent;
use App\Models\Software;
use App\Models\SoftwareInstallation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Applies an agent report to the agent's own device only: agent-sourced rows the agent no longer
 * reports are removed, manual/CSV rows stay (their version and last-seen are refreshed).
 */
class AgentCheckinService
{
    /**
     * @param  array{platform?: string|null, agent_version?: string|null, software: list<array{name: string, version?: string|null, vendor?: string|null, installed_at?: string|null}>, antivirus?: array{product?: string|null, state: string, definitions_updated_at?: string|null, last_scan_at?: string|null}|null}  $payload
     */
    public function apply(DeviceAgent $agent, array $payload, ?string $ip): int
    {
        return DB::transaction(function () use ($agent, $payload, $ip): int {
            $now = CarbonImmutable::now();
            $asset = Asset::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($agent->asset_id);

            $reported = [];
            foreach ($payload['software'] as $item) {
                $reported[Software::normalize($item['name'])] = $item;
            }
            $catalog = Software::query()->whereIn('normalized_name', array_keys($reported))->get()->keyBy('normalized_name');
            $existing = SoftwareInstallation::query()->where('asset_id', $asset->id)->get()->keyBy('normalized_name');

            foreach ($reported as $normalized => $item) {
                $row = $existing->get($normalized);
                $values = ['version' => $item['version'] ?? null, 'last_seen_at' => $now];
                if ($row !== null) {
                    $row->fill($values)->forceFill(['software_id' => $row->software_id ?? $catalog->get($normalized)?->id])->save();

                    continue;
                }
                SoftwareInstallation::create([
                    ...$values, 'asset_id' => $asset->id, 'software_id' => $catalog->get($normalized)?->id,
                    'name' => mb_substr(trim($item['name']), 0, 255), 'normalized_name' => $normalized,
                    'vendor' => $item['vendor'] ?? null, 'installed_at' => $item['installed_at'] ?? null, 'source' => InstallationSource::Agent,
                ]);
            }
            SoftwareInstallation::query()->where('asset_id', $asset->id)->where('source', InstallationSource::Agent)
                ->whereNotIn('normalized_name', array_keys($reported) ?: [''])->delete();

            if (! empty($payload['antivirus'])) {
                $av = $payload['antivirus'];
                $product = isset($av['product']) ? Software::query()->where('normalized_name', Software::normalize((string) $av['product']))->first() : null;
                AntivirusStatus::query()->updateOrCreate(['asset_id' => $asset->id], [
                    'software_id' => $product?->id, 'product_name' => $av['product'] ?? null, 'state' => $av['state'],
                    'definitions_updated_at' => $av['definitions_updated_at'] ?? null, 'last_scan_at' => $av['last_scan_at'] ?? null,
                    'reported_at' => $now, 'source' => InstallationSource::Agent, 'reported_by' => null,
                ]);
            }

            $asset->forceFill(['software_inventoried_at' => $now])->save();
            $agent->forceFill([
                'platform' => $payload['platform'] ?? $agent->platform, 'agent_version' => $payload['agent_version'] ?? $agent->agent_version,
                'last_checkin_at' => $now, 'last_ip' => $ip,
            ])->save();
            AgentCheckin::create(['agent_id' => $agent->id, 'ip' => $ip, 'status' => 'accepted', 'software_count' => count($reported), 'received_at' => $now]);

            return count($reported);
        });
    }

    public function reject(DeviceAgent $agent, ?string $ip, string $message): void
    {
        AgentCheckin::create(['agent_id' => $agent->id, 'ip' => $ip, 'status' => 'rejected', 'message' => mb_substr($message, 0, 255), 'received_at' => CarbonImmutable::now()]);
    }
}
