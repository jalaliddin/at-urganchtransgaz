<?php

namespace App\Http\Requests\Requisitions;

use App\Enums\RequisitionItemKind;
use App\Models\Employee;
use App\Models\Requisition;
use App\Rules\AccessibleOrganization;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Requisition draft (create and edit). */
class RequisitionRequest extends FormRequest
{
    /** Creating needs the permission before validation; edits are checked by the workflow (requester, draft). */
    public function authorize(): bool
    {
        return ! $this->isMethod('POST') || $this->user()?->can('requisitions.create') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $today = CarbonImmutable::now((string) config('app.display_timezone'))->toDateString();

        return [
            'organization_id' => $creating ? ['required', 'integer', new AccessibleOrganization] : ['prohibited'],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'needed_by' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'recipient_employee_id' => ['nullable', 'integer'],
            'items' => [$creating ? 'required' : 'sometimes', 'array', 'min:1', 'max:50'],
            'items.*.kind' => ['required', Rule::enum(RequisitionItemKind::class)],
            'items.*.material_id' => ['nullable', 'integer', Rule::exists('materials', 'id')],
            'items.*.asset_category_id' => ['nullable', 'integer', Rule::exists('asset_categories', 'id')],
            'items.*.name' => ['required_without:items.*.material_id', 'nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:1000000'],
            'items.*.unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('recipient_employee_id')) {
                return;
            }
            $requisition = $this->route('requisition');
            $organizationId = $requisition instanceof Requisition ? $requisition->organization_id : $this->integer('organization_id');
            if (! Employee::query()->withoutGlobalScopes()->whereKey($this->integer('recipient_employee_id'))->where('organization_id', $organizationId)->exists()) {
                $validator->errors()->add('recipient_employee_id', __('messages.recipient_employee_invalid'));
            }
        }];
    }

    /** @return list<array<string, mixed>>|null */
    public function items(): ?array
    {
        if (! $this->has('items')) {
            return null;
        }
        /** @var array<int, array<string, mixed>> $items */
        $items = $this->validated('items');

        return array_values($items);
    }
}
