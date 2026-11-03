<?php

namespace App\Http\Requests\Helpdesk;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CalendarSettingsRequest extends FormRequest
{
    public const KEYS = [
        'work_days', 'work_start', 'work_end', 'lunch_start', 'lunch_end', 'holidays',
        'auto_close_days', 'reopen_days', 'due_soon_minutes',
    ];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('helpdesk.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $definitions = (array) config('settings');
        $rules = [];
        foreach (self::KEYS as $key) {
            $definition = $definitions["helpdesk.{$key}"];
            $rules[$key] = $definition['rules'];
            if (isset($definition['item_rules'])) {
                $rules["{$key}.*"] = $definition['item_rules'];
            }
        }

        return $rules;
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            [$start, $end] = [$this->string('work_start'), $this->string('work_end')];
            [$lunchStart, $lunchEnd] = [$this->string('lunch_start'), $this->string('lunch_end')];
            // H:i strings compare correctly as text.
            if ($end <= $start) {
                $validator->errors()->add('work_end', __('messages.calendar_end_before_start'));
            }
            if ($lunchStart < $start || $lunchStart >= $end) {
                $validator->errors()->add('lunch_start', __('messages.calendar_lunch_outside'));
            }
            if ($lunchEnd <= $lunchStart || $lunchEnd > $end) {
                $validator->errors()->add('lunch_end', __('messages.calendar_lunch_outside'));
            }
        }];
    }

    /** @return array<string, mixed> setting key => value */
    public function settingValues(): array
    {
        $values = [];
        foreach (self::KEYS as $key) {
            $values["helpdesk.{$key}"] = $this->input($key);
        }

        return $values;
    }
}
