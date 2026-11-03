<?php

namespace App\Http\Requests\Dashboard;

use App\Rules\AccessibleOrganization;
use Illuminate\Foundation\Http\FormRequest;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('dashboard.view');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['organization_id' => ['nullable', 'integer', new AccessibleOrganization(false)]];
    }
}
