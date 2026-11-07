<?php

namespace App\Http\Requests\Assets;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Rules\AccessibleOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Create (POST) and edit (PATCH) an asset card. The organization changes only by transfer. */
class AssetRequest extends FormRequest
{
    public const HOSTNAME = '/^[A-Za-z0-9][A-Za-z0-9.\-]{0,99}$/';

    public const MAC = '/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/';

    public function authorize(): bool
    {
        $asset = $this->asset();

        return $asset === null
            ? (bool) $this->user()?->can('create', Asset::class)
            : (bool) $this->user()?->can('update', $asset);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = $this->asset() === null;
        $req = $creating ? 'required' : 'sometimes';
        $orgId = $this->organizationId() ?? 0;
        $sameOrg = fn (string $table) => Rule::exists($table, 'id')->where('organization_id', $orgId);

        return [
            'organization_id' => $creating ? ['required', 'integer', new AccessibleOrganization] : ['prohibited'],
            'status' => $creating ? ['sometimes', Rule::in([AssetStatus::InStock->value, AssetStatus::Reserve->value])] : ['prohibited'],
            'inventory_number' => ['nullable', 'string', 'max:50', Rule::unique('assets', 'inventory_number')->ignore($this->asset()?->id)],
            'name' => [$req, 'string', 'max:200'],
            'category_id' => [$req, 'integer', Rule::exists('asset_categories', 'id')],
            'model_id' => ['nullable', 'integer', Rule::exists('asset_models', 'id')],
            'manufacturer_id' => ['nullable', 'integer', Rule::exists('manufacturers', 'id')],
            'department_id' => ['nullable', 'integer', $sameOrg('departments')->whereNull('deleted_at')],
            'location_id' => ['nullable', 'integer', $sameOrg('locations')],
            'responsible_employee_id' => ['nullable', 'integer', $sameOrg('employees')->whereNull('deleted_at')],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'hostname' => ['nullable', 'string', 'max:100', 'regex:'.self::HOSTNAME],
            'ip_address' => ['nullable', 'ip'],
            'mac_address' => ['nullable', 'string', 'regex:'.self::MAC],
            'vlan' => ['nullable', 'string', 'max:50'],
            'subnet' => ['nullable', 'string', 'max:50'],
            'room' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['nullable', 'date_format:Y-m-d'],
            'warranty_until' => ['nullable', 'date_format:Y-m-d'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'supplier' => ['nullable', 'string', 'max:200'],
            'specs' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('model_id') || ! $this->filled('model_id')) {
                return;
            }
            $categoryId = $this->input('category_id', $this->asset()?->category_id);
            $modelCategory = AssetModel::query()->whereKey($this->integer('model_id'))->value('category_id');
            if ((int) $modelCategory !== (int) $categoryId) {
                $validator->errors()->add('model_id', __('messages.asset_model_category'));
            }
        }];
    }

    public function asset(): ?Asset
    {
        $asset = $this->route('asset');

        return $asset instanceof Asset ? $asset : null;
    }

    private function organizationId(): ?int
    {
        $asset = $this->asset();
        if ($asset !== null) {
            return $asset->organization_id;
        }
        $id = filter_var($this->input('organization_id'), FILTER_VALIDATE_INT);

        return $id === false ? null : $id;
    }
}
