<?php

namespace App\Http\Resources\Software;

use App\Models\AntivirusStatus;
use App\Models\Software;
use App\Models\SoftwareInstallation;
use App\Models\SoftwareLicense;
use App\Models\SoftwarePolicy;

/** Plain arrays for software data. Licence keys are only ever masked here. */
final class SoftwareResource
{
    /** @return array<string, mixed> */
    public static function software(Software $s): array
    {
        return [
            'id' => $s->id, 'name' => $s->name, 'vendor' => $s->vendor, 'category' => $s->category->value,
            'license_type' => $s->license_type->value, 'is_antivirus' => $s->is_antivirus, 'description' => $s->description,
            'is_active' => $s->is_active,
            'installations_count' => array_key_exists('installations_count', $s->getAttributes()) ? (int) $s->getAttribute('installations_count') : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function license(SoftwareLicense $l, int $used): array
    {
        return [
            'id' => $l->id, 'number' => $l->number,
            'software' => ['id' => $l->software->id, 'name' => $l->software->name],
            'organization' => ['id' => $l->organization->id, 'name' => $l->organization->name],
            'license_key_masked' => self::mask($l->license_key), 'has_key' => $l->license_key !== null && $l->license_key !== '',
            'seats' => $l->seats, 'used' => $used, 'free' => max(0, $l->seats - $used),
            'valid_from' => $l->valid_from?->toDateString(), 'expires_at' => $l->expires_at?->toDateString(),
            'supplier' => $l->supplier, 'cost' => $l->cost, 'note' => $l->note,
        ];
    }

    /** @return array<string, mixed> */
    public static function installation(SoftwareInstallation $i): array
    {
        return [
            'id' => $i->id, 'name' => $i->name, 'vendor' => $i->vendor, 'version' => $i->version,
            'software' => $i->software ? ['id' => $i->software->id, 'name' => $i->software->name, 'is_antivirus' => $i->software->is_antivirus] : null,
            'source' => $i->source->value,
            'license' => $i->license ? ['id' => $i->license->id, 'number' => $i->license->number] : null,
            'installed_at' => $i->installed_at?->toDateString(), 'last_seen_at' => $i->last_seen_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function policy(SoftwarePolicy $p): array
    {
        return [
            'id' => $p->id, 'software' => ['id' => $p->software->id, 'name' => $p->software->name],
            'organization' => $p->organization ? ['id' => $p->organization->id, 'name' => $p->organization->name] : null,
            'asset_category' => $p->assetCategory ? ['id' => $p->assetCategory->id, 'name' => $p->assetCategory->localizedName()] : null,
            'note' => $p->note,
        ];
    }

    /** @return array<string, mixed> */
    public static function antivirus(?AntivirusStatus $a): array
    {
        return [
            'effective' => AntivirusStatus::effective($a)->value,
            'state' => $a?->state->value,
            'product' => $a === null ? null : ($a->software->name ?? $a->product_name),
            'software_id' => $a?->software_id,
            'definitions_updated_at' => $a?->definitions_updated_at?->toIso8601String(),
            'last_scan_at' => $a?->last_scan_at?->toIso8601String(),
            'reported_at' => $a?->reported_at->toIso8601String(),
            'source' => $a?->source->value,
        ];
    }

    /** "XXXX-…-AB12": only the last four characters stay readable. */
    public static function mask(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        return str_repeat('•', 8).mb_substr($key, -4);
    }
}
