<?php

namespace App\Services\Exports;

use App\Models\Requisition;
use App\Models\User;
use App\Services\Requisitions\RequisitionQueries;
use App\Support\OrganizationSubtree;

/** Requisitions export (same filters and visibility as the list screen). */
class RequisitionReport implements ReportDefinition
{
    public function permission(): string
    {
        return 'requisitions.export';
    }

    public function filterNames(): array
    {
        return ['tab', 'status', 'organization', 'search'];
    }

    public function title(string $locale): string
    {
        return __('exports.requisitions.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(
            fn (string $key): string => __("exports.requisitions.columns.{$key}", [], $locale),
            ['number', 'created_at', 'organization', 'title', 'status', 'requester', 'items', 'needed_by', 'submitted_at', 'approved_at', 'received_at'],
        );
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        $tz = (string) config('app.display_timezone');
        $query = Requisition::query()->visibleTo($user)->with(['organization', 'requester'])->withCount('items');
        match ($filters['tab'] ?? null) {
            'mine' => $query->where('requester_id', $user->id),
            'action' => RequisitionQueries::awaitingAction($query, $user),
            default => null,
        };
        if (! empty($filters['status'])) {
            $query->whereIn('status', explode(',', (string) $filters['status']));
        }
        if (! empty($filters['organization'])) {
            $query->whereIn('organization_id', OrganizationSubtree::ids((int) $filters['organization']) ?: [0]);
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('number', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"));
        }

        foreach ($query->lazyById(500, 'requisitions.id', 'id') as $r) {
            /** @var Requisition $r */
            yield [
                $r->number,
                $r->created_at->setTimezone($tz)->format('d.m.Y H:i'),
                $r->organization->name,
                $r->title,
                __('enums.requisition_status.'.$r->status->value, [], $locale),
                $r->requester?->name,
                (int) $r->getAttribute('items_count'),
                $r->needed_by?->format('d.m.Y'),
                $r->submitted_at?->setTimezone($tz)->format('d.m.Y H:i'),
                $r->approved_at?->setTimezone($tz)->format('d.m.Y H:i'),
                $r->received_at?->setTimezone($tz)->format('d.m.Y H:i'),
            ];
        }
    }
}
