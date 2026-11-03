<?php

namespace App\Services\Organizations;

use App\Exceptions\DomainConflictException;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationItResponsible;
use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use App\Support\Access\OrganizationAccessResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    public function __construct(private readonly OrganizationAccessResolver $access) {}

    /** @param array<string, mixed> $data validated */
    public function create(array $data): Organization
    {
        $organization = DB::transaction(fn (): Organization => Organization::create($data)->refresh());
        $this->access->flush(); // the new node may fall inside cached descendant scopes

        return $organization;
    }

    /** @param array<string, mixed> $data validated; a changed parent_id moves the subtree */
    public function update(Organization $organization, array $data): Organization
    {
        return DB::transaction(function () use ($organization, $data): Organization {
            $organization->fill(Arr::except($data, ['parent_id']))->save();

            if (array_key_exists('parent_id', $data) && $data['parent_id'] !== $organization->parent_id) {
                $this->move($organization, $data['parent_id'] === null ? null : (int) $data['parent_id']);
            }

            return $organization->refresh();
        });
    }

    /**
     * Re-parents $organization and rewrites path/depth of its whole subtree.
     * Callers validate that the new parent is not inside the subtree.
     */
    public function move(Organization $organization, ?int $newParentId): void
    {
        DB::transaction(function () use ($organization, $newParentId): void {
            $current = DB::table('organizations')->where('id', $organization->id)->lockForUpdate()->first(['path', 'depth']);
            $parent = $newParentId === null ? null
                : DB::table('organizations')->where('id', $newParentId)->lockForUpdate()->first(['path', 'depth']);

            $oldPrefix = (string) $current->path;
            $newPrefix = ($parent->path ?? '/').$organization->id.'/';
            $delta = ($parent === null ? 0 : (int) $parent->depth + 1) - (int) $current->depth;

            $organization->parent_id = $newParentId;
            $organization->save(); // audited parent change

            DB::update(
                'UPDATE organizations SET path = CONCAT(?, SUBSTRING(path, CHAR_LENGTH(?) + 1)), depth = depth + ? WHERE path LIKE ?',
                [$newPrefix, $oldPrefix, $delta, $oldPrefix.'%'],
            );
        });

        $this->access->flush();
    }

    /** @throws DomainConflictException when the organization still has children, departments or employees */
    public function delete(Organization $organization): void
    {
        // Only live rows count (soft-deleted ones keep history, not the organization).
        $live = fn (string $model) => $model::query()->withoutGlobalScope(OrganizationScope::class);
        $busy = $live(Organization::class)->where('parent_id', $organization->id)->exists()
            || $live(Department::class)->where('organization_id', $organization->id)->exists()
            || $live(Employee::class)->where('organization_id', $organization->id)->exists();

        if ($busy) {
            throw new DomainConflictException(__('messages.organization_not_empty'));
        }
        // Users homed here would become invisible to every scoped administrator.
        if ($live(User::class)->where('organization_id', $organization->id)->exists()) {
            throw new DomainConflictException(__('messages.organization_has_users'));
        }

        DB::transaction(fn () => $organization->delete());
        $this->access->flush();
    }

    /**
     * Accessible organizations as a forest; a node whose parent is out of scope becomes a root.
     *
     * @return list<array<string, mixed>>
     */
    public function tree(): array
    {
        $organizations = Organization::query()->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'short_name', 'type', 'is_active']);
        $ids = $organizations->pluck('id')->flip();

        $byParent = $organizations->groupBy(
            fn (Organization $o): int => $o->parent_id !== null && $ids->has($o->parent_id) ? $o->parent_id : 0,
        );

        $build = function (int $parentKey) use (&$build, $byParent): array {
            $nodes = [];
            foreach ($byParent->get($parentKey, []) as $organization) {
                $nodes[] = [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'short_name' => $organization->getAttribute('short_name'),
                    'type' => $organization->type->value,
                    'is_active' => $organization->is_active,
                    'children' => $build($organization->id),
                ];
            }

            return $nodes;
        };

        return $build(0);
    }

    public function assignItResponsible(Organization $organization, Employee $employee, bool $isPrimary, string $assignedAt, ?string $notes): OrganizationItResponsible
    {
        return DB::transaction(function () use ($organization, $employee, $isPrimary, $assignedAt, $notes): OrganizationItResponsible {
            if ($isPrimary) {
                OrganizationItResponsible::where('organization_id', $organization->id)
                    ->where('is_primary', true)
                    ->get()
                    ->each(fn (OrganizationItResponsible $row) => $row->update(['is_primary' => false]));
            }

            return OrganizationItResponsible::create([
                'organization_id' => $organization->id,
                'employee_id' => $employee->id,
                'is_primary' => $isPrimary,
                'assigned_at' => $assignedAt,
                'notes' => $notes,
            ])->refresh();
        });
    }

    public function removeItResponsible(OrganizationItResponsible $assignment): void
    {
        DB::transaction(fn () => $assignment->delete());
    }
}
