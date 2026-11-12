<?php

use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\Admin\EventController as AdminEventController;
use App\Http\Controllers\Api\V1\Admin\ProfileController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\TicketTypeController as AdminTicketTypeController;
use App\Http\Controllers\Api\V1\Auth\ConfirmPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MfaController;
use App\Http\Controllers\Api\V1\Public\CheckoutController;
use App\Http\Controllers\Api\V1\Public\EventController as PublicEventController;
use App\Http\Controllers\Api\V1\Public\HealthController;
use App\Http\Controllers\Api\V1\Public\PaymentController;
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

    // Event Management (Phase 3)
    Route::get('/events', [AdminEventController::class, 'index'])->name('api.v1.admin.events.index');
    Route::post('/events', [AdminEventController::class, 'store'])->name('api.v1.admin.events.store');
    Route::get('/events/{event}', [AdminEventController::class, 'show'])->name('api.v1.admin.events.show');
    Route::patch('/events/{event}', [AdminEventController::class, 'update'])->name('api.v1.admin.events.update');
    Route::post('/events/{event}/publish', [AdminEventController::class, 'publish'])->name('api.v1.admin.events.publish');
    Route::post('/events/{event}/unpublish', [AdminEventController::class, 'unpublish'])->name('api.v1.admin.events.unpublish');
    Route::post('/events/{event}/cancel', [AdminEventController::class, 'cancel'])->name('api.v1.admin.events.cancel');
    Route::post('/events/{event}/archive', [AdminEventController::class, 'archive'])->name('api.v1.admin.events.archive');
    Route::post('/events/{event}/banner', [AdminEventController::class, 'uploadBanner'])->name('api.v1.admin.events.banner');

    // Ticket Types Management (Phase 3)
    Route::get('/events/{event}/ticket-types', [AdminTicketTypeController::class, 'index'])->name('api.v1.admin.ticket-types.index');
    Route::post('/events/{event}/ticket-types', [AdminTicketTypeController::class, 'store'])->name('api.v1.admin.ticket-types.store');
    Route::get('/events/{event}/ticket-types/{ticketType}', [AdminTicketTypeController::class, 'show'])->name('api.v1.admin.ticket-types.show');
    Route::patch('/events/{event}/ticket-types/{ticketType}', [AdminTicketTypeController::class, 'update'])->name('api.v1.admin.ticket-types.update');
    Route::post('/events/{event}/ticket-types/{ticketType}/archive', [AdminTicketTypeController::class, 'archive'])->name('api.v1.admin.ticket-types.archive');
});

// Public Route Group (Phase 3: Events Catalog, Phase 4: Checkout)
Route::prefix('public')->group(function () {
    Route::get('/events', [PublicEventController::class, 'index'])->name('api.v1.public.events.index');
    Route::get('/events/{slug}', [PublicEventController::class, 'show'])->name('api.v1.public.events.show');

    // Phase 4: Anonymous Checkout & Order Retrieval
    Route::post('/checkout', [CheckoutController::class, 'checkout'])
        ->middleware('throttle:15,1')
        ->name('api.v1.public.checkout');
    Route::get('/orders/{orderNumber}', [CheckoutController::class, 'show'])
        ->middleware('throttle:30,1')
        ->name('api.v1.public.orders.show');

    // Phase 5: Mobile Money Payments
    Route::post('/payments/initiate', [PaymentController::class, 'initiate'])
        ->middleware('throttle:10,1')
        ->name('api.v1.public.payments.initiate');
    Route::post('/payments/submit-otp', [PaymentController::class, 'submitOtp'])
        ->middleware('throttle:10,1')
        ->name('api.v1.public.payments.submit-otp');
    Route::get('/payments/{reference}/status', [PaymentController::class, 'status'])
        ->middleware('throttle:60,1')
        ->name('api.v1.public.payments.status');

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
