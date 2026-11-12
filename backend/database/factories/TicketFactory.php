<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\OrderItem;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'order_item_id' => OrderItem::factory(),
            'event_id' => Event::factory(),
            'ticket_number' => 'TKT-' . strtoupper(Str::random(10)),
            'qr_token_hash' => hash('sha256', Str::random(64)),
            'holder_name' => fake()->name(),
            'holder_email' => fake()->safeEmail(),
            'status' => TicketStatus::ACTIVE,
            'issued_at' => now(),
            'used_at' => null,
        ];
    }
}

