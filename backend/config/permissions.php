<?php

/*
|--------------------------------------------------------------------------
| Permission registry and default roles
|--------------------------------------------------------------------------
| Permissions are `module.action` and map to code features, so they are declared
| here. Roles are data: RbacSeeder creates the defaults below once; afterwards a
| Super Admin edits them in the UI. Permissions added here later are granted to
| the default roles that list them on the next seed (see RbacSeeder).
| Matrix: docs/DATABASE.md §4.
*/

return [

    'modules' => [
        'dashboard' => ['view'],
        'organizations' => ['view', 'create', 'update', 'delete', 'manage_it_staff'],
        'departments' => ['view', 'create', 'update', 'delete'],
        'employees' => ['view', 'create', 'update', 'delete'],
        'users' => ['view', 'create', 'update', 'deactivate', 'reset_password', 'manage_access'],
        'roles' => ['view', 'manage'],
        'audit' => ['view'],
        'settings' => ['view', 'update'],
        'helpdesk' => ['view', 'create', 'process', 'assign', 'export', 'manage'],
        'documents' => ['view', 'manage'],
    ],

    'roles' => [
        'super-admin' => [
            'display_name' => 'Super Admin',
            'description' => 'Tizimning barcha sozlamalari va barcha ma\'lumotlari.',
            'permissions' => '*',
        ],
        'central-it-head' => [
            'display_name' => 'Markaziy AT rahbari',
            'description' => 'Barcha tashkilotlar, foydalanuvchilar, xodimlar, hisobotlar va audit.',
            'permissions' => [
                'dashboard.view',
                'organizations.view', 'organizations.create', 'organizations.update', 'organizations.delete',
                'organizations.manage_it_staff',
                'departments.view', 'departments.create', 'departments.update', 'departments.delete',
                'employees.view', 'employees.create', 'employees.update', 'employees.delete',
                'users.view', 'users.create', 'users.update', 'users.deactivate', 'users.reset_password',
                'users.manage_access',
                'roles.view', 'audit.view', 'settings.view',
                'helpdesk.view', 'helpdesk.create', 'helpdesk.process', 'helpdesk.assign', 'helpdesk.export',
                'helpdesk.manage',
                'documents.view', 'documents.manage',
            ],
        ],
        'central-it-staff' => [
            'display_name' => 'Markaziy AT xodimi',
            'description' => 'Biriktirilgan modullar va vazifalar doirasida ishlaydi.',
            'permissions' => [
                'dashboard.view', 'organizations.view', 'departments.view',
                'employees.view', 'employees.create', 'employees.update',
                'users.view',
                'helpdesk.view', 'helpdesk.create', 'helpdesk.process', 'helpdesk.export',
                'documents.view', 'documents.manage',
            ],
        ],
        'org-it-responsible' => [
            'display_name' => 'Quyi tashkilot AT mas\'uli',
            'description' => 'O\'z tashkiloti xodimlari, bo\'limlari va AT resurslarini yuritadi.',
            'permissions' => [
                'dashboard.view', 'organizations.view',
                'departments.view', 'departments.create', 'departments.update', 'departments.delete',
                'employees.view', 'employees.create', 'employees.update', 'employees.delete',
                'helpdesk.view', 'helpdesk.create', 'helpdesk.process', 'helpdesk.assign', 'helpdesk.export',
                'documents.view', 'documents.manage',
            ],
        ],
        'org-head' => [
            'display_name' => 'Quyi tashkilot rahbari',
            'description' => 'O\'z tashkiloti ma\'lumotlarini ko\'radi va belgilangan jarayonlarda tasdiqlaydi.',
            'permissions' => [
                'dashboard.view', 'organizations.view', 'departments.view', 'employees.view',
                'helpdesk.view', 'helpdesk.create', 'helpdesk.export',
            ],
        ],
        'employee' => [
            'display_name' => 'Oddiy xodim',
            'description' => 'Helpdesk arizasi yuboradi, o\'z arizalari va aktivlarini ko\'radi.',
            'permissions' => ['dashboard.view', 'helpdesk.create'],
        ],
        'accountant' => [
            'display_name' => 'Buxgalter / moddiy javobgar shaxs',
            'description' => 'Kirim-chiqim, hisobdan chiqarish va moliyaviy ma\'lumotlar.',
            'permissions' => [
                'dashboard.view', 'organizations.view', 'departments.view', 'employees.view',
                'helpdesk.create', 'documents.view', 'documents.manage',
            ],
        ],
        'auditor' => [
            'display_name' => 'Auditor / kuzatuvchi',
            'description' => 'Faqat o\'qish va hisobot olish huquqi.',
            'permissions' => [
                'dashboard.view', 'organizations.view', 'departments.view', 'employees.view',
                'users.view', 'roles.view', 'audit.view', 'settings.view',
                'helpdesk.view', 'helpdesk.export', 'documents.view',
            ],
        ],
    ],

];
