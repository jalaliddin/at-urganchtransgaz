<?php

namespace App\Http\Requests\Settings;

use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('settings.update');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = ['values' => ['required', 'array']];

        foreach (app(SettingsService::class)->definitions() as $key => $definition) {
            // Partial updates: each key is optional, but a required setting may not be blanked.
            // Setting keys contain dots; escape them so they are not read as nesting.
            $rules['values.'.str_replace('.', '\.', $key)] = ['sometimes', ...array_map(
                fn (string $rule): string => $rule === 'required' ? 'filled' : $rule,
                $definition['rules'],
            )];
        }

        return $rules;
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $known = app(SettingsService::class)->definitions();
            foreach (array_keys((array) $this->input('values', [])) as $key) {
                if (! isset($known[$key])) {
                    $validator->errors()->add("values.{$key}", __('validation.in', ['attribute' => $key]));
                }
            }
        }];
    }

    /** @return array<string, mixed> */
    public function settingValues(): array
    {
        $definitions = app(SettingsService::class)->definitions();

        return array_intersect_key((array) $this->input('values', []), $definitions);
    }
}
