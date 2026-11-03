<?php

use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Departments\DepartmentController;
use App\Http\Controllers\Api\V1\Employees\EmployeeController;
use App\Http\Controllers\Api\V1\Health\HealthController;
use App\Http\Controllers\Api\V1\Organizations\ItResponsibleController;
use App\Http\Controllers\Api\V1\Organizations\OrganizationController;
use App\Http\Controllers\Api\V1\Organizations\OrganizationTreeController;
use App\Http\Controllers\Api\V1\Settings\PublicSettingsController;
use App\Http\Controllers\Api\V1\Settings\SettingsController;
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
});
