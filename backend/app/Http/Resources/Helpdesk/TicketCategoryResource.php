<?php

namespace App\Http\Resources\Helpdesk;

use App\Models\TicketCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketCategory */
class TicketCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var TicketCategory $category */
        $category = $this->resource;

        return [
            'id' => $category->id,
            'code' => $category->code,
            'name' => $category->name,
            'name_ru' => $category->name_ru,
            'label' => $category->localizedName(),
            'description' => $category->description,
            'is_active' => $category->is_active,
            'sort_order' => $category->sort_order,
        ];
    }
}
