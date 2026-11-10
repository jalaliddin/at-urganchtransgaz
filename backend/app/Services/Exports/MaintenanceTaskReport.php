<?php

namespace App\Services\Exports;

use App\Models\MaintenanceTask;
use App\Models\User;
use App\Services\Itsm\ItsmReports;

/** Preventive maintenance work items of the user's organizations. */
class MaintenanceTaskReport implements ReportDefinition
{
    public function __construct(private readonly ItsmReports $reports) {}

    public function permission(): string
    {
        return 'maintenance.export';
    }

    public function filterNames(): array
    {
        return ['status', 'organization', 'plan', 'due_from', 'due_to'];
    }

    public function title(string $locale): string
    {
        return __('exports.maintenance_tasks.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(fn (string $k) => __("exports.maintenance_tasks.columns.{$k}", [], $locale),
            ['plan', 'asset', 'organization', 'due_on', 'status', 'completed_on', 'completed_by', 'result', 'note']);
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        $query = $this->reports->within(MaintenanceTask::query()->visibleTo($user), empty($filters['organization']) ? null : (int) $filters['organization'])
            ->with(['plan', 'asset', 'organization', 'completer'])
            ->when(! empty($filters['status']), fn ($q) => $q->whereIn('status', explode(',', (string) $filters['status'])))
            ->when(! empty($filters['plan']), fn ($q) => $q->where('plan_id', (int) $filters['plan']))
            ->when(! empty($filters['due_from']), fn ($q) => $q->where('due_on', '>=', (string) $filters['due_from']))
            ->when(! empty($filters['due_to']), fn ($q) => $q->where('due_on', '<=', (string) $filters['due_to']))
            ->orderBy('due_on')->orderBy('id');
        foreach ($query->lazy(500) as $t) {
            yield [
                $t->plan->title, $t->asset->inventory_number.' — '.$t->asset->name, $t->organization->name, $t->due_on->format('d.m.Y'),
                __('enums.maintenance_task_status.'.$t->status->value, [], $locale), $t->completed_on?->format('d.m.Y'), $t->completer?->name,
                $t->result === null ? null : __('enums.maintenance_result.'.$t->result->value, [], $locale), $t->note,
            ];
        }
    }
}
