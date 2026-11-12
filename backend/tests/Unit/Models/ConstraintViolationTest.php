<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Admin;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConstraintViolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_ends_at_must_be_after_starts_at(): void
    {
        $this->expectException(QueryException::class);

        $admin = Admin::factory()->create();

        Event::create([
            'id' => (string) Str::uuid(),
            'name' => 'Invalid Event',
            'slug' => 'invalid-event',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(2), // Invalid: ends before starts
            'timezone' => 'Africa/Accra',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
    }

    public function test_single_admission_check_in_unique_constraint(): void
    {
        $ticket = Ticket::factory()->create();
        $staff1 = Admin::factory()->create();
        $staff2 = Admin::factory()->create();

        CheckIn::create([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'admin_id' => $staff1->id,
            'checked_in_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        // Second check-in for same ticket MUST violate UNIQUE(ticket_id)
        CheckIn::create([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'admin_id' => $staff2->id,
            'checked_in_at' => now(),
        ]);
    }

    public function test_ticket_inventory_balance_constraint(): void
    {
        $this->expectException(QueryException::class);

        $event = Event::factory()->create();

        // Total 100, but sold 80 + reserved 30 = 110 > 100 -> violates check_ticket_inventory_balance
        TicketType::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'name' => 'Oversold Category',
            'price_minor' => 1000,
            'quantity_total' => 100,
            'quantity_sold' => 80,
            'quantity_reserved' => 30,
            'min_per_order' => 1,
            'status' => 'active',
        ]);
    }
}

