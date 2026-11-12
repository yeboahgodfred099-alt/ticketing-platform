<?php

use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\Admin\ProfileController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Auth\ConfirmPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MfaController;
use App\Http\Controllers\Api\V1\Public\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Version 1
|--------------------------------------------------------------------------
*/

// System Health Check
Route::get('/health', HealthController::class)->name('api.v1.health');

// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/login', LoginController::class)->name('api.v1.auth.login');
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', LogoutController::class)->name('api.v1.auth.logout');
        Route::post('/mfa/setup', [MfaController::class, 'setup'])->name('api.v1.auth.mfa.setup');
        Route::post('/mfa/confirm', [MfaController::class, 'confirmSetup'])->name('api.v1.auth.mfa.confirm');
        Route::post('/mfa/verify', [MfaController::class, 'verify'])->name('api.v1.auth.mfa.verify');
        Route::post('/confirm-password', ConfirmPasswordController::class)->name('api.v1.auth.confirm-password');
    });
});

// Admin Route Group (Requires Sanctum auth + Verified MFA)
Route::prefix('admin')->middleware(['auth:sanctum', 'mfa'])->group(function () {
    // Current Admin Profile
    Route::get('/me', [ProfileController::class, 'show'])->name('api.v1.admin.me');

    // Admin Account Management
    Route::get('/users', [AdminController::class, 'index'])->name('api.v1.admin.users.index');
    Route::post('/users', [AdminController::class, 'store'])->name('api.v1.admin.users.store');
    Route::get('/users/{admin}', [AdminController::class, 'show'])->name('api.v1.admin.users.show');
    Route::patch('/users/{admin}', [AdminController::class, 'update'])->name('api.v1.admin.users.update');
    Route::patch('/users/{admin}/toggle-active', [AdminController::class, 'toggleActive'])
        ->middleware('reauth')
        ->name('api.v1.admin.users.toggle-active');

    // Roles and Permissions Catalog
    Route::get('/roles', [RoleController::class, 'index'])->name('api.v1.admin.roles.index');
});

// Public Route Group
Route::prefix('public')->group(function () {
    // Phase 3: Events
    // Phase 4: Checkout
    // Phase 5: Payment status
    // Phase 7: Ticket retrieval
});

// Gate Staff Route Group
Route::prefix('staff')->middleware('auth:sanctum')->group(function () {
    // Phase 8: Gate scan & atomic check-in
});

// Paystack Webhooks Route Group
Route::prefix('webhooks')->group(function () {
    // Phase 6: Paystack webhook
});
