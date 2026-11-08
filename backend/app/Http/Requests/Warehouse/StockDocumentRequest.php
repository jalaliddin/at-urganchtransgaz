<?php

namespace App\Http\Requests\Warehouse;

use App\Enums\StockDocumentType;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\StockDocument;
use App\Models\User;
use App\Models\Warehouse;
use App\Rules\AccessibleOrganization;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shape and scope of a stock document. Warehouses (source and target) must be in
 * the user's organizations; the recipient employee and device must belong to the
 * recipient organization. Stock rules are StockService's.
 */
class StockDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = $this->input('type');
        $today = CarbonImmutable::now((string) config('app.display_timezone'))->toDateString();

        return [
            'type' => ['required', Rule::enum(StockDocumentType::class)],
            'warehouse_id' => ['required', 'integer', $this->visibleWarehouse()],
            'target_warehouse_id' => [Rule::requiredIf($type === 'transfer'), 'nullable', 'integer', 'different:warehouse_id', $this->visibleWarehouse()],
            'document_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'supplier' => ['nullable', 'string', 'max:255'],
            'supplier_document' => ['nullable', 'string', 'max:100'],
            'recipient_organization_id' => ['nullable', 'integer', new AccessibleOrganization],
            'recipient_employee_id' => ['nullable', 'integer'],
            'asset_id' => ['nullable', 'integer'],
            'reason' => [Rule::requiredIf(in_array($type, ['issue', 'adjustment'], true)), 'nullable', 'string', 'max:1000'],
            'related_document_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.material_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'between:-999999999999,999999999999'],
            'lines.*.batch' => ['nullable', 'string', 'max:100'],
            'lines.*.expires_at' => ['nullable', 'date_format:Y-m-d'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999'],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $user = $this->user();
            if (! $user instanceof User) {
                return;
            }
            $warehouseOrg = Warehouse::query()->whereKey($this->integer('warehouse_id'))->value('organization_id');
            $recipientOrg = $this->filled('recipient_organization_id') ? $this->integer('recipient_organization_id') : (int) $warehouseOrg;

            if ($this->filled('recipient_employee_id')
                && ! Employee::query()->withoutGlobalScopes()->whereKey($this->integer('recipient_employee_id'))->where('organization_id', $recipientOrg)->exists()) {
                $validator->errors()->add('recipient_employee_id', __('messages.recipient_employee_invalid'));
            }
            if ($this->filled('asset_id')
                && ! Asset::query()->visibleTo($user)->whereKey($this->integer('asset_id'))->where('organization_id', $recipientOrg)->exists()) {
                $validator->errors()->add('asset_id', __('messages.recipient_asset_invalid'));
            }
            if ($this->filled('related_document_id')
                && ! StockDocument::query()->visibleTo($user)->whereKey($this->integer('related_document_id'))->exists()) {
                $validator->errors()->add('related_document_id', __('messages.return_needs_issue'));
            }
        }];
    }

    public function documentType(): StockDocumentType
    {
        return StockDocumentType::from((string) $this->input('type'));
    }

    /** @return array<string, mixed> */
    public function documentHeader(): array
    {
        $type = $this->documentType();
        $fields = match ($type) {
            StockDocumentType::Receipt => ['supplier', 'supplier_document'],
            StockDocumentType::Issue => ['recipient_organization_id', 'recipient_employee_id', 'asset_id', 'reason'],
            StockDocumentType::Return => ['related_document_id', 'recipient_employee_id', 'reason'],
            StockDocumentType::Transfer => ['target_warehouse_id', 'reason'],
            StockDocumentType::Adjustment => ['reason'],
        };

        return array_filter($this->safe()->only(['warehouse_id', 'document_date', ...$fields]), fn ($v) => $v !== null);
    }

    /** @return array<int, array<string, mixed>> */
    public function documentLines(): array
    {
        /** @var array<int, array<string, mixed>> $lines */
        $lines = $this->validated('lines');

        return array_values($lines);
    }

    /** The warehouse must exist in the user's organizations (global scope applies). */
    private function visibleWarehouse(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! Warehouse::query()->whereKey((int) $value)->exists()) {
                $fail(__('messages.warehouse_not_accessible'));
            }
        };
    }
}
