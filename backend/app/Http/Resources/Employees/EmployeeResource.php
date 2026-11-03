<?php

namespace App\Http\Resources\Employees;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    use FormatsDates;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Employee $employee */
        $employee = $this->resource;
        $user = $request->user();

        return [
            'id' => $employee->id,
            'organization' => $this->whenLoaded('organization', fn () => $employee->organization
                ? ['id' => $employee->organization->id, 'name' => $employee->organization->name]
                : null),
            'department' => $this->whenLoaded('department', fn () => $employee->department
                ? ['id' => $employee->department->id, 'name' => $employee->department->name]
                : null),
            'last_name' => $employee->last_name,
            'first_name' => $employee->first_name,
            'middle_name' => $employee->middle_name,
            'full_name' => $employee->full_name,
            'position' => $employee->getAttribute('position'),
            'personnel_number' => $employee->getAttribute('personnel_number'),
            'phone' => $employee->getAttribute('phone'),
            'internal_phone' => $employee->getAttribute('internal_phone'),
            'email' => $employee->getAttribute('email'),
            'status' => $employee->status->value,
            'status_label' => $employee->status->label(),
            'hired_at' => $this->date($employee->getAttribute('hired_at')),
            'dismissed_at' => $this->date($employee->getAttribute('dismissed_at')),
            'notes' => $employee->getAttribute('notes'),
            'user' => $this->whenLoaded('user', fn () => $employee->user ? [
                'id' => $employee->user->id,
                'username' => $employee->user->username,
                'is_active' => $employee->user->is_active,
            ] : null),
            'created_at' => $this->iso($employee->created_at),
            'updated_at' => $this->iso($employee->updated_at),
            'can' => [
                'update' => (bool) $user?->can('update', $employee),
                'delete' => (bool) $user?->can('delete', $employee),
            ],
        ];
    }
}
