<?php

/*
|--------------------------------------------------------------------------
| System settings registry
|--------------------------------------------------------------------------
| Every runtime setting is declared here with its type, default, validation
| rules and whether it is public (served to the login page without auth).
| system_settings stores only overridden values. See SettingsService.
| `array` settings validate each element with `item_rules`. `helpdesk.*` keys are
| edited on the Helpdesk settings screen (helpdesk.manage), the rest on Settings.
*/

return [

    'general.system_name' => [
        'type' => 'string',
        'default' => 'AT.urtg.uz',
        'rules' => ['required', 'string', 'max:100'],
        'public' => true,
    ],

    'general.organization_name' => [
        'type' => 'string',
        'default' => '"Urganchtransgaz" MCHJ',
        'rules' => ['required', 'string', 'max:255'],
        'public' => true,
    ],

    'general.support_phone' => [
        'type' => 'string',
        'default' => null,
        'rules' => ['nullable', 'string', 'max:50'],
        'public' => true,
    ],

    'general.support_email' => [
        'type' => 'string',
        'default' => null,
        'rules' => ['nullable', 'email', 'max:255'],
        'public' => true,
    ],

    'security.password_min_length' => [
        'type' => 'integer',
        'default' => 10,
        'rules' => ['required', 'integer', 'min:8', 'max:64'],
        'public' => false,
    ],

    'security.password_expires_days' => [
        'type' => 'integer',
        'default' => 0,
        'rules' => ['required', 'integer', 'min:0', 'max:365'],
        'public' => false,
    ],

    'helpdesk.work_days' => [
        'type' => 'array',
        'default' => [1, 2, 3, 4, 5],
        'rules' => ['required', 'array', 'min:1', 'max:7'],
        'item_rules' => ['required', 'integer', 'between:1,7', 'distinct'],
        'public' => false,
    ],

    'helpdesk.work_start' => [
        'type' => 'string',
        'default' => '09:00',
        'rules' => ['required', 'date_format:H:i'],
        'public' => false,
    ],

    'helpdesk.work_end' => [
        'type' => 'string',
        'default' => '18:00',
        'rules' => ['required', 'date_format:H:i'],
        'public' => false,
    ],

    'helpdesk.lunch_start' => [
        'type' => 'string',
        'default' => '13:00',
        'rules' => ['required', 'date_format:H:i'],
        'public' => false,
    ],

    'helpdesk.lunch_end' => [
        'type' => 'string',
        'default' => '14:00',
        'rules' => ['required', 'date_format:H:i'],
        'public' => false,
    ],

    'helpdesk.holidays' => [
        'type' => 'array',
        'default' => [],
        'rules' => ['present', 'array', 'max:100'],
        'item_rules' => ['required', 'date_format:Y-m-d', 'distinct'],
        'public' => false,
    ],

    'helpdesk.auto_close_days' => [
        'type' => 'integer',
        'default' => 3,
        'rules' => ['required', 'integer', 'min:0', 'max:60'],
        'public' => false,
    ],

    'helpdesk.reopen_days' => [
        'type' => 'integer',
        'default' => 7,
        'rules' => ['required', 'integer', 'min:0', 'max:90'],
        'public' => false,
    ],

    'helpdesk.due_soon_minutes' => [
        'type' => 'integer',
        'default' => 60,
        'rules' => ['required', 'integer', 'min:0', 'max:1440'],
        'public' => false,
    ],

    'files.max_upload_mb' => [
        'type' => 'integer',
        'default' => 10,
        // 5 files × 20 MB per request must fit nginx/PHP limits (scripts/check-upload-limits.sh).
        'rules' => ['required', 'integer', 'min:1', 'max:20'],
        'public' => true,
    ],

    // Requisitions go to the organization head first; skipped when the organization has no active approver.
    'requisitions.head_approval' => [
        'type' => 'boolean',
        'default' => true,
        'rules' => ['required', 'boolean'],
        'public' => false,
    ],

    'notifications.mail_enabled' => [
        'type' => 'boolean',
        'default' => true,
        'rules' => ['required', 'boolean'],
        'public' => false,
    ],

];
