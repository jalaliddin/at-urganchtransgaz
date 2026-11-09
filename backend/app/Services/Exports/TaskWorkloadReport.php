<?php

namespace App\Services\Exports;

use App\Models\User;
use App\Services\Tasks\TaskReports;

/** Workload per responsible person (open, overdue, due within 7 days, done in 30 days). */
class TaskWorkloadReport implements ReportDefinition
{
    public function __construct(private readonly TaskReports $reports) {}

    public function permission(): string
    {
        return 'tasks.workload';
    }

    public function filterNames(): array
    {
        return ['organization', 'board'];
    }

    public function title(string $locale): string
    {
        return __('exports.task_workload.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(fn (string $k) => __("exports.task_workload.columns.{$k}", [], $locale), ['user', 'open', 'overdue', 'due_week', 'done_30']);
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        foreach ($this->reports->workload($user, $filters)['by_assignee'] as $row) {
            yield [$row['user']['name'] ?? __('exports.task_workload.unassigned', [], $locale), $row['open'], $row['overdue'], $row['due_week'], $row['done_30']];
        }
    }
}
