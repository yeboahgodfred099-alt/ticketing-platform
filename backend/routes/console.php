<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduled Commands
|--------------------------------------------------------------------------
*/

// Phase 4: Inventory reservation expiration (runs every minute)
Schedule::job(new \App\Jobs\ExpireReservations)->everyMinute();

// Phase 6: Payment reconciliation (runs every 5 minutes)
Schedule::job(new \App\Jobs\ReconcilePayments)->everyFiveMinutes();

