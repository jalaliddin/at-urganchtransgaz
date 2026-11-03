<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use App\Rules\AccessibleOrganization;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->user();
        $organization = $this->organization();

        if (! $user->can('update', $organization)) {
            return false;
        }

        // Moving a subtree requires access to every organization in it.
        return ! $this->isMove() || $user->accessScope()->coversSubtreeOf($organization);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();
        $organization = $this->organization();

        return [
            'parent_id' => [
                'sometimes',
                Rule::requiredIf(fn (): bool => ! $user->accessScope()->isUnrestricted()),
                'nullable', 'integer', new AccessibleOrganization,
                function (string $attribute, mixed $value, Closure $fail) use ($organization): void {
                    $targetPath = DB::table('organizations')->where('id', $value)->value('path');
                    if (is_string($targetPath) && str_starts_with($targetPath, $organization->path)) {
                        $fail(__('messages.organization_cycle'));
                    }
                },
            ],
            ...StoreOrganizationRequest::attributeRules(),
            'type' => ['sometimes', Rule::enum(OrganizationType::class)],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', 'regex:/^[A-Za-z0-9._\-]+$/', Rule::unique('organizations', 'code')->ignore($organization->id)],
            'tin' => ['nullable', 'digits:9', Rule::unique('organizations', 'tin')->ignore($organization->id)],
        ];
    }

    private function organization(): Organization
    {
        /** @var Organization */
        return $this->route('organization');
    }

    private function isMove(): bool
    {
        return $this->has('parent_id') && $this->input('parent_id') != $this->organization()->parent_id;
    }
}
