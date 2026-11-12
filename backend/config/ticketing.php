<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ticketing Platform Core Configuration
    |--------------------------------------------------------------------------
    |
    | Authoritative rules for inventory reservation, timeout, and fees.
    |
    */

    // Duration an inventory reservation is held before being automatically released
    'reservation_ttl_minutes' => (int) env('RESERVATION_TTL_MINUTES', 15),

    // Customer prompt timeout window on phone (180s per Paystack Ghana MoMo documentation)
    'payment_timeout_seconds' => (int) env('PAYMENT_TIMEOUT_SECONDS', 180),

    // Reconciliation settings
    'reconciliation_interval_minutes' => (int) env('RECONCILIATION_INTERVAL_MINUTES', 5),
    'reconciliation_pending_threshold_minutes' => (int) env('RECONCILIATION_PENDING_THRESHOLD_MINUTES', 10),

    // Default currency and timezone
    'currency' => 'GHS',
    'timezone' => 'Africa/Accra',

    // Paystack fees handling: absorbed by organizer (customer does NOT pay fees)
    'fees_absorbed_by_organizer' => true,
];

