<?php

namespace App\Enums;

enum AuditEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';
    case PasswordChanged = 'password_changed';
    case PasswordReset = 'password_reset';
    case RolesChanged = 'roles_changed';
    case AccessChanged = 'access_changed';
    case SettingsUpdated = 'settings_updated';
    case RolePermissionsChanged = 'role_permissions_changed';
    case SecretRevealed = 'secret_revealed';

    public function label(): string
    {
        return __('enums.audit_event.'.$this->value);
    }
}
