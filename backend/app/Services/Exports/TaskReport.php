<?php

namespace App\Services\Exports;

use App\Models\User;
use App\Services\Tasks\TaskReports;

/** Tasks of the boards the user can see. */
class TaskReport implements ReportDefinition
{
    public function __construct(private readonly TaskReports $reports) {}

    public function permission(): string
    {
        return 'tasks.export';
    }

    public function filterNames(): array
    {
        return ['board', 'organization', 'assignee', 'open', 'overdue'];
    }

    public function title(string $locale): string
    {
        return __('exports.tasks.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(fn (string $k) => __("exports.tasks.columns.{$k}", [], $locale),
            ['number', 'title', 'board', 'column', 'organization', 'assignee', 'priority', 'project', 'due_date', 'overdue', 'completed_at']);
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        $today = $this->reports->today();
        $query = $this->reports->scoped($user, $filters)->with(['board', 'column', 'organization', 'assignee'])
            ->when(! empty($filters['assignee']), fn ($q) => $q->where('assignee_id', (int) $filters['assignee']))
            ->when(filter_var($filters['open'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($q) => $q->whereNull('completed_at'))
            ->when(filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($q) => $q->whereNull('completed_at')->where('due_date', '<', $today))
            ->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('id');
        foreach ($query->lazy(500) as $t) {
            yield [
                $t->number, $t->title, $t->board->name, $t->column->name, $t->organization->name, $t->assignee?->name,
                __('enums.task_priority.'.$t->priority->value, [], $locale), $t->project, $t->due_date?->format('d.m.Y'),
                $t->isOverdue() ? __('exports.yes', [], $locale) : '', $t->completed_at?->format('d.m.Y'),
            ];
        }
    }
}
