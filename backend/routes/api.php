<?php

use App\Http\Controllers\Api\V1\Health\HealthController;
use App\Http\Controllers\Api\V1\Settings\PublicSettingsController;
use App\Http\Controllers\Api\V1\Settings\SettingsController;
use Illuminate\Support\Facades\Route;

// API v1 routes (prefix api/v1 is set in bootstrap/app.php).

Route::middleware('throttle:public')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');
    Route::get('settings/public', PublicSettingsController::class)->name('settings.public');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
});
