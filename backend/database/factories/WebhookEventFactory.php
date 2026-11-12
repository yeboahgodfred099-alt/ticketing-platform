<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WebhookStatus;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'provider' => 'paystack',
            'event_identifier' => 'evt_' . Str::random(16),
            'event_type' => 'charge.success',
            'payment_id' => Payment::factory(),
            'payload_hash' => hash('sha256', Str::random(100)),
            'processing_status' => WebhookStatus::RECEIVED,
            'received_at' => now(),
            'processed_at' => null,
            'last_error_code' => null,
        ];
    }
}

