<?php

namespace App\Services\Exports;

use App\Models\Change;
use App\Models\User;
use App\Services\Itsm\ItsmReports;

/** Changes of the user's organizations. */
class ChangeReport implements ReportDefinition
{
    public function __construct(private readonly ItsmReports $reports) {}

    public function permission(): string
    {
        return 'changes.export';
    }

    public function filterNames(): array
    {
        return ['status', 'type', 'organization'];
    }

    public function title(string $locale): string
    {
        return __('exports.changes.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(fn (string $k) => __("exports.changes.columns.{$k}", [], $locale),
            ['number', 'title', 'organization', 'type', 'category', 'risk', 'status', 'planned_start', 'planned_end', 'implementer', 'result']);
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        $tz = (string) config('app.display_timezone');
        $query = $this->reports->within(Change::query()->visibleTo($user), empty($filters['organization']) ? null : (int) $filters['organization'])
            ->with(['organization', 'implementer'])
            ->when(! empty($filters['status']), fn ($q) => $q->whereIn('status', explode(',', (string) $filters['status'])))
            ->when(! empty($filters['type']), fn ($q) => $q->whereIn('type', explode(',', (string) $filters['type'])))
            ->orderBy('id');
        foreach ($query->lazy(500) as $c) {
            yield [
                $c->number, $c->title, $c->organization->name, __('enums.change_type.'.$c->type->value, [], $locale),
                __('enums.change_category.'.$c->category->value, [], $locale), __('enums.change_risk.'.$c->risk->value, [], $locale),
                __('enums.change_status.'.$c->status->value, [], $locale), $c->planned_start?->setTimezone($tz)->format('d.m.Y H:i'),
                $c->planned_end?->setTimezone($tz)->format('d.m.Y H:i'), $c->implementer?->name, $c->result,
            ];
        }
    }
}
