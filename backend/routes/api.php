<?php

use App\Http\Controllers\Api\V1\Health\HealthController;
use Illuminate\Support\Facades\Route;

// API v1 routes (prefix api/v1 is set in bootstrap/app.php).

Route::middleware('throttle:public')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');
});
