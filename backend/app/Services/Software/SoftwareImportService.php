<?php

namespace App\Services\Software;

use App\Enums\AntivirusState;
use App\Enums\InstallationSource;
use App\Models\AntivirusStatus;
use App\Models\Asset;
use App\Models\Software;
use App\Models\SoftwareInstallation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * CSV import of installed software or antivirus status by inventory number (spec S7). Only
 * devices in the importer's organizations are touched; an unknown and an out-of-scope inventory
 * number get the same error, so the import does not reveal other organizations' devices.
 */
class SoftwareImportService
{
    public const COLUMNS = [
        'installations' => ['inventory_number', 'name', 'version', 'vendor', 'installed_at'],
        'antivirus' => ['inventory_number', 'product', 'state', 'definitions_updated_at', 'last_scan_at'],
    ];

    private const REQUIRED = ['installations' => ['inventory_number', 'name'], 'antivirus' => ['inventory_number', 'state']];

    private const MAX_ERRORS = 200;

    /** @return array{total: int, imported: int, errors: list<array{line: int, message: string}>} */
    public function import(string $type, string $path, User $by): array
    {
        [$header, $rows] = $this->read($path);
        $missing = array_diff(self::REQUIRED[$type], $header);
        if ($missing !== []) {
            throw ValidationException::withMessages(['file' => __('messages.import_columns', ['columns' => implode(', ', self::COLUMNS[$type])])]);
        }
        if (count($rows) > (int) config('software.import_max_rows')) {
            throw ValidationException::withMessages(['file' => __('messages.import_too_many', ['max' => (int) config('software.import_max_rows')])]);
        }

        $numbers = array_values(array_unique(array_filter(array_map(fn ($r) => trim((string) ($r['inventory_number'] ?? '')), $rows))));
        $assets = Asset::query()->visibleTo($by)->whereIn('inventory_number', $numbers ?: [''])->get()->keyBy('inventory_number');
        $errors = [];
        $imported = 0;

        DB::transaction(function () use ($type, $rows, $assets, $by, &$errors, &$imported): void {
            $touched = [];
            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $asset = $assets->get(trim((string) ($row['inventory_number'] ?? '')));
                try {
                    if ($asset === null) {
                        throw new \InvalidArgumentException(__('messages.import_unknown_device'));
                    }
                    $type === 'installations' ? $this->installation($asset, $row, $by) : $this->antivirus($asset, $row, $by);
                    $touched[$asset->id] = true;
                    $imported++;
                } catch (\InvalidArgumentException $e) {
                    if (count($errors) < self::MAX_ERRORS) {
                        $errors[] = ['line' => $line, 'message' => $e->getMessage()];
                    }
                }
            }
            if ($type === 'installations' && $touched !== []) {
                Asset::query()->withoutGlobalScopes()->whereIn('id', array_keys($touched))->update(['software_inventoried_at' => CarbonImmutable::now()]);
            }
        });

        return ['total' => count($rows), 'imported' => $imported, 'errors' => $errors];
    }

    /** @param  array<string, string>  $row */
    private function installation(Asset $asset, array $row, User $by): void
    {
        $name = trim($row['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 255) {
            throw new \InvalidArgumentException(__('messages.import_name_required'));
        }
        $normalized = Software::normalize($name);
        $values = [
            'version' => $this->text($row['version'] ?? null, 100), 'vendor' => $this->text($row['vendor'] ?? null, 150),
            'installed_at' => $this->date($row['installed_at'] ?? null)?->toDateString(), 'last_seen_at' => CarbonImmutable::now(),
        ];
        $existing = SoftwareInstallation::query()->where('asset_id', $asset->id)->where('normalized_name', $normalized)->first();
        if ($existing !== null) {
            $existing->fill(array_filter($values, fn ($v) => $v !== null))->save();

            return;
        }
        SoftwareInstallation::create([
            ...$values, 'asset_id' => $asset->id, 'name' => $name, 'normalized_name' => $normalized, 'source' => InstallationSource::Csv,
            'software_id' => Software::query()->where('normalized_name', $normalized)->value('id'), 'created_by' => $by->id,
        ]);
    }

    /** @param  array<string, string>  $row */
    private function antivirus(Asset $asset, array $row, User $by): void
    {
        $state = AntivirusState::tryFrom(mb_strtolower(trim($row['state'] ?? '')));
        if ($state === null) {
            throw new \InvalidArgumentException(__('messages.import_state_invalid'));
        }
        $product = $this->text($row['product'] ?? null, 150);
        AntivirusStatus::query()->updateOrCreate(['asset_id' => $asset->id], [
            'software_id' => $product === null ? null : Software::query()->where('normalized_name', Software::normalize($product))->where('is_antivirus', true)->value('id'),
            'product_name' => $product, 'state' => $state,
            'definitions_updated_at' => $this->date($row['definitions_updated_at'] ?? null),
            'last_scan_at' => $this->date($row['last_scan_at'] ?? null),
            'reported_at' => CarbonImmutable::now(), 'source' => InstallationSource::Csv, 'reported_by' => $by->id,
        ]);
    }

    /**
     * Header (lower-cased) and rows as column => value; comma or semicolon delimited, UTF-8 BOM tolerated.
     *
     * @return array{0: list<string>, 1: list<array<string, string>>}
     */
    private function read(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => __('messages.import_unreadable')]);
        }
        $first = (string) fgets($handle);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), str_getcsv(trim($first), $delimiter));
        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($data === [null] || $data === ['']) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $column) {
                $row[$column] = trim((string) ($data[$i] ?? ''));
            }
            $rows[] = $row;
        }
        fclose($handle);

        return [$header, $rows];
    }

    private function text(?string $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function date(?string $value): ?CarbonImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = null;
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd.m.Y H:i', 'd.m.Y'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $value, (string) config('app.display_timezone'));
                if ($parsed !== null && $parsed->format($format) === $value) {
                    $date = $parsed;
                    break;
                }
            } catch (Throwable) {
            }
        }
        if ($date === null) {
            throw new \InvalidArgumentException(__('messages.import_date_invalid', ['value' => $value]));
        }
        // A future date would keep the device "updated" for ever (a day of clock/timezone slack).
        if ($date->isAfter(CarbonImmutable::now()->addDay())) {
            throw new \InvalidArgumentException(__('messages.import_date_future', ['value' => $value]));
        }

        return $date->utc();
    }
}
