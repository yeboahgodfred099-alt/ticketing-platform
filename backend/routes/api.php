<?php

use App\Http\Controllers\Api\V1\Public\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Version 1
|--------------------------------------------------------------------------
|
| Base path: /api/v1
| All route groups follow the architectural specification in docs/ARCHITECTURE.md
|
*/

// System Health Check
Route::get('/health', HealthController::class)->name('api.v1.health');

// Public Route Group
Route::prefix('public')->group(function () {
    // Phase 3: Events
    // Phase 4: Checkout
    // Phase 5: Payment status
    // Phase 7: Ticket retrieval
});

// Paystack Webhooks Route Group
Route::prefix('webhooks')->group(function () {
    // Phase 6: Paystack webhook
});

// Admin Route Group (Sanctum stateful auth + MFA)
Route::prefix('admin')->group(function () {
    // Phase 2: Auth, Roles, Permissions, Admin management
    // Phase 3: Event & TicketType management
    // Phase 9: Reports & Audit logs
});

// Gate Staff Route Group (Sanctum stateful auth)
Route::prefix('staff')->group(function () {
    // Phase 8: Gate scan & atomic check-in
});

