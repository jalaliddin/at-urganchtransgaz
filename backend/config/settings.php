<?php

/*
|--------------------------------------------------------------------------
| System settings registry
|--------------------------------------------------------------------------
| Every runtime setting is declared here with its type, default, validation
| rules and whether it is public (served to the login page without auth).
| system_settings stores only overridden values. See SettingsService.
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

];
