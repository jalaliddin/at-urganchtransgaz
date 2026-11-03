<?php

namespace App\Http\Resources\Organizations;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\OrganizationItResponsible;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganizationItResponsible
 */
class ItResponsibleResource extends JsonResource
{
    use FormatsDates;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var OrganizationItResponsible $row */
        $row = $this->resource;
        $employee = $row->employee;

        return [
            'id' => $row->id,
            'organization_id' => $row->organization_id,
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'position' => $employee->getAttribute('position'),
                'phone' => $employee->getAttribute('phone'),
                'email' => $employee->getAttribute('email'),
                'organization' => $employee->organization
                    ? ['id' => $employee->organization->id, 'name' => $employee->organization->name]
                    : null,
            ] : null,
            'is_primary' => $row->is_primary,
            'assigned_at' => $this->date($row->getAttribute('assigned_at')),
            'notes' => $row->getAttribute('notes'),
        ];
    }
}
