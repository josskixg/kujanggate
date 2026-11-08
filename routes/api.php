<?php

declare(strict_types=1);

use App\Http\Controllers\LicenseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/licenses/issue', [LicenseController::class, 'issue']);
    Route::post('/licenses/verify-offline', [LicenseController::class, 'verifyOffline']);
    Route::post('/webhooks/order-paid', [LicenseController::class, 'handleOrderWebhook']);
});
