<?php

use App\Http\Controllers\Api\V1\Audit\AuditLogController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\Departments\DepartmentController;
use App\Http\Controllers\Api\V1\Employees\EmployeeController;
use App\Http\Controllers\Api\V1\Files\FileDownloadController;
use App\Http\Controllers\Api\V1\Health\HealthController;
use App\Http\Controllers\Api\V1\Helpdesk\CalendarSettingsController;
use App\Http\Controllers\Api\V1\Helpdesk\SlaPolicyController;
use App\Http\Controllers\Api\V1\Helpdesk\TicketCategoryController;
use App\Http\Controllers\Api\V1\Notifications\NotificationController;
use App\Http\Controllers\Api\V1\Organizations\ItResponsibleController;
use App\Http\Controllers\Api\V1\Organizations\OrganizationController;
use App\Http\Controllers\Api\V1\Organizations\OrganizationTreeController;
use App\Http\Controllers\Api\V1\Roles\PermissionController;
use App\Http\Controllers\Api\V1\Roles\RoleController;
use App\Http\Controllers\Api\V1\Search\SearchController;
use App\Http\Controllers\Api\V1\Settings\PublicSettingsController;
use App\Http\Controllers\Api\V1\Settings\SettingsController;
use App\Http\Controllers\Api\V1\Users\UserController;
use App\Http\Controllers\Api\V1\Users\UserOrganizationAccessController;
use App\Http\Controllers\Api\V1\Users\UserPasswordResetController;
use App\Http\Controllers\Api\V1\Users\UserRolesController;
use App\Http\Controllers\Api\V1\Users\UserStatusController;
use Illuminate\Support\Facades\Route;

// API v1 routes (prefix api/v1 is set in bootstrap/app.php).

Route::middleware('throttle:public')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');
    Route::get('settings/public', PublicSettingsController::class)->name('settings.public');
});

Route::post('auth/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('auth.login');

Route::middleware('throttle:password-reset')->group(function (): void {
    Route::post('auth/forgot-password', ForgotPasswordController::class)->name('auth.password.forgot');
    Route::post('auth/reset-password', ResetPasswordController::class)->name('auth.password.reset');
});

Route::middleware(['auth:sanctum', 'active', 'password.changed'])->group(function (): void {
    Route::post('auth/logout', [LoginController::class, 'destroy'])->name('auth.logout');
    Route::get('auth/me', [MeController::class, 'show'])->name('auth.me');
    Route::patch('auth/me', [MeController::class, 'update'])->name('auth.me.update');
    Route::put('auth/password', [PasswordController::class, 'update'])->name('auth.password.update');

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('files/{uuid}', FileDownloadController::class)->whereUuid('uuid')->name('files.download');
    Route::get('search', SearchController::class)->name('search');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->whereNumber('auditLog')->name('audit-logs.show');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('organizations/tree', OrganizationTreeController::class)->name('organizations.tree');
    Route::apiResource('organizations', OrganizationController::class);
    Route::get('organizations/{organization}/it-responsibles', [ItResponsibleController::class, 'index'])
        ->name('organizations.it-responsibles.index');
    Route::post('organizations/{organization}/it-responsibles', [ItResponsibleController::class, 'store'])
        ->name('organizations.it-responsibles.store');
    Route::delete('organizations/{organization}/it-responsibles/{employee}', [ItResponsibleController::class, 'destroy'])
        ->whereNumber('employee')->name('organizations.it-responsibles.destroy');

    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('employees', EmployeeController::class);

    Route::apiResource('users', UserController::class)->except(['destroy']);
    Route::post('users/{user}/deactivate', [UserStatusController::class, 'deactivate'])->name('users.deactivate');
    Route::post('users/{user}/activate', [UserStatusController::class, 'activate'])->name('users.activate');
    Route::post('users/{user}/reset-password', UserPasswordResetController::class)->name('users.reset-password');
    Route::put('users/{user}/roles', UserRolesController::class)->name('users.roles.update');
    Route::put('users/{user}/organization-access', UserOrganizationAccessController::class)->name('users.access.update');

    Route::get('permissions', PermissionController::class)->name('permissions.index');
    Route::apiResource('roles', RoleController::class);

    Route::apiResource('ticket-categories', TicketCategoryController::class)->except(['show']);
    Route::apiResource('ticket-sla-policies', SlaPolicyController::class)->except(['show']);
    Route::get('helpdesk/calendar', [CalendarSettingsController::class, 'show'])->name('helpdesk.calendar.show');
    Route::put('helpdesk/calendar', [CalendarSettingsController::class, 'update'])->name('helpdesk.calendar.update');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->whereUuid('notification')->name('notifications.read');
});
