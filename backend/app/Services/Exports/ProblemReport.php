<?php

namespace App\Services\Exports;

use App\Models\Problem;
use App\Models\User;
use App\Services\Itsm\ItsmReports;

/** Problems of the user's organizations. */
class ProblemReport implements ReportDefinition
{
    public function __construct(private readonly ItsmReports $reports) {}

    public function permission(): string
    {
        return 'problems.export';
    }

    public function filterNames(): array
    {
        return ['status', 'priority', 'organization'];
    }

    public function title(string $locale): string
    {
        return __('exports.problems.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(fn (string $k) => __("exports.problems.columns.{$k}", [], $locale),
            ['number', 'title', 'organization', 'category', 'priority', 'status', 'assignee', 'tickets', 'created_at', 'resolved_at', 'root_cause']);
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        $query = $this->reports->within(Problem::query()->visibleTo($user), empty($filters['organization']) ? null : (int) $filters['organization'])
            ->with(['organization', 'category', 'assignee'])->withCount('tickets')
            ->when(! empty($filters['status']), fn ($q) => $q->whereIn('status', explode(',', (string) $filters['status'])))
            ->when(! empty($filters['priority']), fn ($q) => $q->whereIn('priority', explode(',', (string) $filters['priority'])))
            ->orderBy('id');
        foreach ($query->lazy(500) as $p) {
            yield [
                $p->number, $p->title, $p->organization->name, $p->category?->localizedName($locale),
                __('enums.problem_priority.'.$p->priority->value, [], $locale), __('enums.problem_status.'.$p->status->value, [], $locale),
                $p->assignee?->name, (int) ($p->getAttributes()['tickets_count'] ?? 0), $p->created_at->format('d.m.Y'),
                $p->resolved_at?->format('d.m.Y'), $p->root_cause,
            ];
        }
    }
}
