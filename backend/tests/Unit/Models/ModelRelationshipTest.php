<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_roles_and_permissions_relationship(): void
    {
        $admin = Admin::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();

        $role->permissions()->attach($permission->id);
        $admin->roles()->attach($role->id);

        $this->assertTrue($admin->roles->contains($role));
        $this->assertTrue($role->permissions->contains($permission));
        $this->assertTrue($admin->hasRole($role->name));
        $this->assertTrue($admin->hasPermission($permission->name));
    }

    public function test_event_ticket_types_relationship(): void
    {
        $event = Event::factory()->create();
        $ticketType = TicketType::factory()->create(['event_id' => $event->id]);

        $this->assertTrue($event->ticketTypes->contains($ticketType));
        $this->assertSame($event->id, $ticketType->event->id);
    }

    public function test_order_items_and_reservations_relationship(): void
    {
        $order = Order::factory()->create();
        $item = OrderItem::factory()->create(['order_id' => $order->id]);
        $reservation = InventoryReservation::factory()->create(['order_id' => $order->id]);

        $this->assertTrue($order->items->contains($item));
        $this->assertTrue($order->reservations->contains($reservation));
    }

    public function test_order_payments_and_webhook_events_relationship(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->create(['order_id' => $order->id]);
        $webhook = WebhookEvent::factory()->create(['payment_id' => $payment->id]);

        $this->assertTrue($order->payments->contains($payment));
        $this->assertTrue($payment->webhookEvents->contains($webhook));
    }

    public function test_ticket_and_check_in_relationship(): void
    {
        $ticket = Ticket::factory()->create();
        $staff = Admin::factory()->create();
        $checkIn = CheckIn::factory()->create([
            'ticket_id' => $ticket->id,
            'admin_id' => $staff->id,
        ]);

        $this->assertSame($ticket->id, $checkIn->ticket->id);
        $this->assertSame($staff->id, $checkIn->staff->id);
        $this->assertSame($checkIn->id, $ticket->checkIn->id);
    }

    public function test_audit_log_relationship(): void
    {
        $admin = Admin::factory()->create();
        $log = AuditLog::factory()->create(['admin_id' => $admin->id]);

        $this->assertSame($admin->id, $log->admin->id);
        $this->assertTrue($admin->auditLogs->contains($log));
    }
}

