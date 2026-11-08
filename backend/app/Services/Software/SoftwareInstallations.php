<?php

namespace App\Services\Software;

use App\Enums\InstallationSource;
use App\Models\Asset;
use App\Models\Software;
use App\Models\SoftwareInstallation;
use App\Models\SoftwareLicense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Software on devices. A row is a catalog title or an unrecognised name; one row per device and
 * normalised name. Licence links lock the licence row so seats are never over-assigned.
 */
class SoftwareInstallations
{
    /** @param  array<string, mixed>  $data  software_id | name, version, vendor, installed_at, license_id */
    public function add(Asset $asset, array $data, User $by, InstallationSource $source = InstallationSource::Manual): SoftwareInstallation
    {
        return DB::transaction(function () use ($asset, $data, $by, $source): SoftwareInstallation {
            $software = $this->resolve($data);
            $name = $software->name ?? trim((string) ($data['name'] ?? ''));
            $normalized = Software::normalize($name);
            if (SoftwareInstallation::query()->where('asset_id', $asset->id)->where('normalized_name', $normalized)->exists()) {
                throw ValidationException::withMessages(['software_id' => __('messages.software_already_installed')]);
            }
            $installation = SoftwareInstallation::create([
                'asset_id' => $asset->id, 'software_id' => $software?->id, 'name' => $name, 'normalized_name' => $normalized,
                'vendor' => $data['vendor'] ?? $software?->vendor, 'version' => $data['version'] ?? null, 'source' => $source,
                'installed_at' => $data['installed_at'] ?? null, 'last_seen_at' => CarbonImmutable::now(), 'created_by' => $by->id,
            ]);
            if (! empty($data['license_id'])) {
                $this->linkLicense($installation, (int) $data['license_id']);
            }

            return $installation;
        });
    }

    /** @param  array<string, mixed>  $data  version, installed_at, license_id (null unlinks) */
    public function update(SoftwareInstallation $installation, array $data): SoftwareInstallation
    {
        return DB::transaction(function () use ($installation, $data): SoftwareInstallation {
            $installation->fill(array_intersect_key($data, array_flip(['version', 'installed_at'])))->save();
            if (array_key_exists('license_id', $data)) {
                $data['license_id'] === null
                    ? $installation->forceFill(['license_id' => null])->save()
                    : $this->linkLicense($installation, (int) $data['license_id']);
            }

            return $installation;
        });
    }

    /** Links every unrecognised row with this normalised name to the catalog title. @return int rows linked */
    public function adopt(Software $software, string $name): int
    {
        return SoftwareInstallation::query()->whereNull('software_id')->where('normalized_name', Software::normalize($name))
            ->update(['software_id' => $software->id, 'updated_at' => CarbonImmutable::now()]);
    }

    /** The licence must be for the same title and have a free seat (counted under its row lock). */
    private function linkLicense(SoftwareInstallation $installation, int $licenseId): void
    {
        $license = SoftwareLicense::query()->withoutGlobalScopes()->lockForUpdate()->find($licenseId);
        if ($license === null || $installation->software_id === null || $license->software_id !== $installation->software_id) {
            throw ValidationException::withMessages(['license_id' => __('messages.license_other_software')]);
        }
        if ($installation->license_id === $license->id) {
            return;
        }
        // A locking read sees links committed while we waited for the licence lock; a plain COUNT would read the
        // REPEATABLE READ snapshot taken by the transaction's first query and miss them.
        $used = SoftwareInstallation::query()->where('license_id', $license->id)->lockForUpdate()->count();
        if ($used >= $license->seats) {
            throw ValidationException::withMessages(['license_id' => __('messages.license_no_seats')]);
        }
        $installation->forceFill(['license_id' => $license->id])->save();
    }

    /** @param  array<string, mixed>  $data */
    private function resolve(array $data): ?Software
    {
        if (! empty($data['software_id'])) {
            return Software::query()->findOrFail((int) $data['software_id']);
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => __('validation.required', ['attribute' => 'name'])]);
        }

        return Software::query()->where('normalized_name', Software::normalize($name))->first();
    }
}
