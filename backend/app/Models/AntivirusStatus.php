<?php

namespace App\Models;

use App\Enums\AntivirusEffective;
use App\Enums\AntivirusState;
use App\Enums\InstallationSource;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Last antivirus report of a device.
 *
 * @property int $id
 * @property int $asset_id
 * @property int|null $software_id
 * @property string|null $product_name
 * @property AntivirusState $state
 * @property CarbonImmutable|null $definitions_updated_at
 * @property CarbonImmutable|null $last_scan_at
 * @property CarbonImmutable $reported_at
 * @property InstallationSource $source
 * @property-read Software|null $software
 */
class AntivirusStatus extends Model
{
    protected $fillable = ['asset_id', 'software_id', 'product_name', 'state', 'definitions_updated_at', 'last_scan_at', 'reported_at', 'source', 'reported_by'];

    protected function casts(): array
    {
        return [
            'state' => AntivirusState::class,
            'source' => InstallationSource::class,
            'definitions_updated_at' => 'immutable_datetime',
            'last_scan_at' => 'immutable_datetime',
            'reported_at' => 'immutable_datetime',
        ];
    }

    /**
     * The status shown everywhere: a stale report is unknown; installed antivirus is updated or
     * outdated by the age of its definitions (setting software.av_outdated_days).
     */
    public static function effective(?self $status, ?CarbonImmutable $now = null): AntivirusEffective
    {
        $now ??= CarbonImmutable::now();
        if ($status === null || $status->reported_at->lt($now->subDays((int) config('software.stale_report_days')))) {
            return AntivirusEffective::Unknown;
        }
        if ($status->state === AntivirusState::NotInstalled) {
            return AntivirusEffective::NotInstalled;
        }
        if ($status->definitions_updated_at === null) {
            return AntivirusEffective::Installed;
        }
        $days = (int) app(SettingsService::class)->get('software.av_outdated_days');

        return $status->definitions_updated_at->lt($now->subDays($days)) ? AntivirusEffective::Outdated : AntivirusEffective::Updated;
    }

    /** @return BelongsTo<Software, $this> */
    public function software(): BelongsTo
    {
        return $this->belongsTo(Software::class);
    }
}
