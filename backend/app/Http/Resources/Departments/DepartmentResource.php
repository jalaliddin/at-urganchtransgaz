<?php

namespace App\Http\Resources\Departments;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Department
 */
class DepartmentResource extends JsonResource
{
    use FormatsDates;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Department $department */
        $department = $this->resource;
        $user = $request->user();

        return [
            'id' => $department->id,
            'organization' => $this->whenLoaded('organization', fn () => $department->organization
                ? ['id' => $department->organization->id, 'name' => $department->organization->name]
                : null),
            'parent' => $this->whenLoaded('parent', fn () => $department->parent
                ? ['id' => $department->parent->id, 'name' => $department->parent->name]
                : null),
            'name' => $department->name,
            'code' => $department->getAttribute('code'),
            'phone' => $department->getAttribute('phone'),
            'is_active' => (bool) $department->getAttribute('is_active'),
            'sort_order' => $department->getAttribute('sort_order'),
            'employees_count' => $this->whenCounted('employees'),
            'created_at' => $this->iso($department->created_at),
            'updated_at' => $this->iso($department->updated_at),
            'can' => [
                'update' => (bool) $user?->can('update', $department),
                'delete' => (bool) $user?->can('delete', $department),
            ],
        ];
    }
}
