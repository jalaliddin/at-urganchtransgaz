<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncOrganizationAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manageAccess', $this->route('user'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = UserRules::accessRows('access');
        $rules['access'] = ['present', 'array'];

        return $rules;
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var User $actor */
            $actor = $this->user();
            UserRules::checkSubtreeCover($validator, $actor, 'access', $this->input('access', []));
        }];
    }

    /** @return list<array{organization_id: int, include_descendants: bool}> */
    public function rows(): array
    {
        return array_values(array_map(fn (array $row): array => [
            'organization_id' => (int) $row['organization_id'],
            'include_descendants' => (bool) $row['include_descendants'],
        ], (array) $this->validated('access', [])));
    }
}
