<?php

namespace App\Services\Settings;

use App\Enums\AuditEvent;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Typed access to the settings declared in config/settings.php.
 */
class SettingsService
{
    private const CACHE_KEY = 'system_settings';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array<string, array{type: string, default: mixed, rules: list<string>, item_rules?: list<string>, public: bool}>
     */
    public function definitions(): array
    {
        /** @var array<string, array{type: string, default: mixed, rules: list<string>, item_rules?: list<string>, public: bool}> */
        return config('settings', []);
    }

    /** @return array<string, mixed> key => effective value */
    public function all(): array
    {
        /** @var array<string, mixed> $stored */
        $stored = Cache::rememberForever(self::CACHE_KEY, fn (): array => SystemSetting::query()
            ->pluck('value', 'key')->all());

        $values = [];
        foreach ($this->definitions() as $key => $definition) {
            $values[$key] = array_key_exists($key, $stored)
                ? $this->cast($definition['type'], $stored[$key])
                : $definition['default'];
        }

        return $values;
    }

    public function get(string $key): mixed
    {
        $all = $this->all();
        if (! array_key_exists($key, $all)) {
            throw new InvalidArgumentException("Unknown setting [{$key}].");
        }

        return $all[$key];
    }

    /** @return array<string, mixed> public settings keyed by their last segment */
    public function publicValues(): array
    {
        $values = $this->all();
        $public = [];
        foreach ($this->definitions() as $key => $definition) {
            if ($definition['public']) {
                $public[substr($key, strrpos($key, '.') + 1)] = $values[$key];
            }
        }

        return $public;
    }

    /** @return list<array{key: string, type: string, value: mixed, default: mixed, public: bool}> */
    public function describe(): array
    {
        $values = $this->all();
        $rows = [];
        foreach ($this->definitions() as $key => $definition) {
            $rows[] = [
                'key' => $key,
                'type' => $definition['type'],
                'value' => $values[$key],
                'default' => $definition['default'],
                'public' => $definition['public'],
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $values  already validated
     */
    public function update(array $values, ?User $actor = null): void
    {
        $definitions = $this->definitions();
        $current = $this->all();
        $old = [];
        $new = [];

        DB::transaction(function () use ($values, $definitions, $current, $actor, &$old, &$new): void {
            foreach ($values as $key => $value) {
                if (! isset($definitions[$key])) {
                    throw new InvalidArgumentException("Unknown setting [{$key}].");
                }
                $value = $this->cast($definitions[$key]['type'], $value);
                if ($value === $current[$key]) {
                    continue;
                }

                SystemSetting::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $actor?->id]);
                $old[$key] = $current[$key];
                $new[$key] = $value;
            }

            if ($new !== []) {
                $this->audit->record(AuditEvent::SettingsUpdated, old: $old, new: $new, actor: $actor);
            }
        });

        Cache::forget(self::CACHE_KEY);
    }

    private function cast(string $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => (bool) $value,
            'array' => array_values((array) $value),
            default => (string) $value,
        };
    }
}
