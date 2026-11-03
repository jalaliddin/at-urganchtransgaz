<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use App\Rules\AccessibleOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Organization::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            // Only unrestricted users (Super Admin) may create a new root.
            'parent_id' => [
                Rule::requiredIf(fn (): bool => ! $user->accessScope()->isUnrestricted()),
                'nullable', 'integer', new AccessibleOrganization,
            ],
            ...self::attributeRules(),
            'type' => ['required', Rule::enum(OrganizationType::class)],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._\-]+$/', Rule::unique('organizations', 'code')],
            'tin' => ['nullable', 'digits:9', Rule::unique('organizations', 'tin')],
        ];
    }

    /** @return array<string, list<mixed>> rules shared with the update request */
    public static function attributeRules(): array
    {
        return [
            'short_name' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^\+?[0-9\s\-()]{5,50}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'head_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
