<?php

namespace App\Http\Resources\Organizations;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organization
 */
class OrganizationResource extends JsonResource
{
    use FormatsDates;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Organization $organization */
        $organization = $this->resource;
        $user = $request->user();

        return [
            'id' => $organization->id,
            'parent_id' => $organization->parent_id,
            'parent' => $this->whenLoaded('parent', fn () => $organization->parent
                ? ['id' => $organization->parent->id, 'name' => $organization->parent->name]
                : null),
            'depth' => $organization->depth,
            'type' => $organization->type->value,
            'type_label' => $organization->type->label(),
            'name' => $organization->name,
            'short_name' => $organization->getAttribute('short_name'),
            'code' => $organization->getAttribute('code'),
            'tin' => $organization->getAttribute('tin'),
            'address' => $organization->getAttribute('address'),
            'phone' => $organization->getAttribute('phone'),
            'email' => $organization->getAttribute('email'),
            'head_name' => $organization->getAttribute('head_name'),
            'is_active' => $organization->is_active,
            'sort_order' => $organization->getAttribute('sort_order'),
            'children_count' => $this->whenCounted('children'),
            'created_at' => $this->iso($organization->created_at),
            'updated_at' => $this->iso($organization->updated_at),
            'can' => [
                'update' => (bool) $user?->can('update', $organization),
                'delete' => (bool) $user?->can('delete', $organization),
                'manage_it_staff' => (bool) $user?->can('manageItStaff', $organization),
            ],
        ];
    }
}
