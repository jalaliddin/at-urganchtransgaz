<?php

namespace App\Support\Permissions;

/**
 * Machine names of the seeded system roles (display names live in config/permissions.php).
 */
final class Roles
{
    public const SUPER_ADMIN = 'super-admin';

    public const CENTRAL_IT_HEAD = 'central-it-head';

    public const CENTRAL_IT_STAFF = 'central-it-staff';

    public const ORG_IT_RESPONSIBLE = 'org-it-responsible';

    public const ORG_HEAD = 'org-head';

    public const EMPLOYEE = 'employee';

    public const ACCOUNTANT = 'accountant';

    public const AUDITOR = 'auditor';

    public const ALL = [
        self::SUPER_ADMIN,
        self::CENTRAL_IT_HEAD,
        self::CENTRAL_IT_STAFF,
        self::ORG_IT_RESPONSIBLE,
        self::ORG_HEAD,
        self::EMPLOYEE,
        self::ACCOUNTANT,
        self::AUDITOR,
    ];
}
