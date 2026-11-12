<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\TicketTypeStatus;
use App\Jobs\ExpireReservations;
use App\Models\Event;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReservationExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_reservations_release_inventory_and_expire_order(): void
    {
        $event = Event::factory()->create(['status' => EventStatus::PUBLISHED]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'quantity_total' => 50,
            'quantity_sold' => 0,
            'quantity_reserved' => 5, // 5 currently reserved
            'status' => TicketTypeStatus::ACTIVE,
        ]);

        $order = Order::factory()->create([
            'event_id' => $event->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'expires_at' => Carbon::now()->subMinutes(5), // Expired 5 minutes ago
        ]);

        $reservation = InventoryReservation::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 5,
            'status' => ReservationStatus::ACTIVE,
            'expires_at' => Carbon::now()->subMinutes(5),
        ]);

        // Run the expiration job
        $job = new ExpireReservations();
        $expiredCount = $job->handle();

        $this->assertSame(1, $expiredCount);

        // Verify reservation marked expired and released
        $reservation->refresh();
        $this->assertSame(ReservationStatus::EXPIRED, $reservation->status);
        $this->assertNotNull($reservation->released_at);

        // Verify reserved count on ticket_types was safely decremented to 0
        $ticketType->refresh();
        $this->assertSame(0, $ticketType->quantity_reserved);

        // Verify order transitioned to EXPIRED
        $order->refresh();
        $this->assertSame(OrderStatus::EXPIRED, $order->status);
    }

    public function test_active_unexpired_reservations_are_not_released(): void
    {
        $event = Event::factory()->create(['status' => EventStatus::PUBLISHED]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'quantity_total' => 50,
            'quantity_reserved' => 3,
            'status' => TicketTypeStatus::ACTIVE,
        ]);

        $order = Order::factory()->create([
            'event_id' => $event->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'expires_at' => Carbon::now()->addMinutes(10), // Expires in future
        ]);

        $reservation = InventoryReservation::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 3,
            'status' => ReservationStatus::ACTIVE,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $job = new ExpireReservations();
        $expiredCount = $job->handle();

        $this->assertSame(0, $expiredCount);

        $reservation->refresh();
        $this->assertSame(ReservationStatus::ACTIVE, $reservation->status);

        $ticketType->refresh();
        $this->assertSame(3, $ticketType->quantity_reserved);

        $order->refresh();
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->status);
    }
}

