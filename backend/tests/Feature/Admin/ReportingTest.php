<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminWithRole(string $roleName): Admin
    {
        $admin = Admin::factory()->create([
            'mfa_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'mfa_enabled_at' => now(),
        ]);

        $role = Role::firstOrCreate(['name' => $roleName]);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_admin_with_permission_can_view_overview_stats(): void
    {
        $admin = $this->createAdminWithRole('Super Admin');
        $event = Event::factory()->published()->create();
        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'price' => 10000, // 100 GHS in pesewas
        ]);

        // Create a paid order with payment
        $order = Order::factory()->create([
            'event_id' => $event->id,
            'status' => OrderStatus::PAID,
            'subtotal' => 20000,
            'total_amount' => 20000,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentStatus::SUCCESSFUL,
            'channel' => 'mobile_money',
            'mobile_money_provider' => 'mtn',
            'amount' => 20000,
            'fee_amount' => 390, // 1.95% absorbed fee
        ]);

        // Create 2 tickets: 1 checked in, 1 active
        Ticket::factory()->create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'status' => TicketStatus::USED,
            'checked_in_at' => now(),
        ]);

        Ticket::factory()->create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'status' => TicketStatus::ACTIVE,
            'checked_in_at' => null,
        ]);

        $response = $this->actingAs($admin, 'web')
            ->withSession(['auth.mfa_verified' => true])
            ->getJson("/api/v1/admin/reports/overview?event_id={$event->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.revenue.gross_pesewas', 20000)
            ->assertJsonPath('data.revenue.absorbed_fees_pesewas', 390)
            ->assertJsonPath('data.revenue.net_pesewas', 19610)
            ->assertJsonPath('data.orders.total_paid_orders', 1)
            ->assertJsonPath('data.tickets.total_sold', 2)
            ->assertJsonPath('data.attendance.checked_in', 1)
            ->assertJsonPath('data.attendance.attendance_rate', 50.0);
    }

    public function test_unauthorized_user_cannot_view_reports(): void
    {
        // Gate Staff lacks reports.view
        $staff = $this->createAdminWithRole('Gate Staff');

        $response = $this->actingAs($staff, 'web')
            ->withSession(['auth.mfa_verified' => true])
            ->getJson('/api/v1/admin/reports/overview');

        $response->assertStatus(403);
    }

    public function test_admin_can_export_orders_csv_stream(): void
    {
        $admin = $this->createAdminWithRole('Super Admin');
        $event = Event::factory()->published()->create();

        Order::factory()->create([
            'event_id' => $event->id,
            'order_number' => 'ORD-TEST-001',
            'customer_name' => 'Kofi Mensah',
            'customer_phone' => '+233241234567',
            'customer_email' => 'kofi@example.com',
            'status' => OrderStatus::PAID,
            'total_amount' => 15000,
        ]);

        $response = $this->actingAs($admin, 'web')
            ->withSession(['auth.mfa_verified' => true])
            ->get("/api/v1/admin/reports/orders/export?event_id={$event->id}");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Order Number', $content);
        $this->assertStringContainsString('ORD-TEST-001', $content);
        $this->assertStringContainsString('Kofi Mensah', $content);
        // Phone should be masked for data privacy
        $this->assertStringNotContainsString('+233241234567', $content);
        $this->assertStringContainsString('***', $content);
    }

    public function test_admin_can_export_attendees_csv_stream(): void
    {
        $admin = $this->createAdminWithRole('Super Admin');
        $event = Event::factory()->published()->create();
        $ticketType = TicketType::factory()->create(['event_id' => $event->id, 'name' => 'VIP Access']);

        $order = Order::factory()->create(['event_id' => $event->id]);
        Ticket::factory()->create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => 'TCK-VIP-999',
            'attendee_name' => 'Ama Serwaa',
            'attendee_phone' => '+233501234567',
            'status' => TicketStatus::ACTIVE,
        ]);

        $response = $this->actingAs($admin, 'web')
            ->withSession(['auth.mfa_verified' => true])
            ->get("/api/v1/admin/reports/attendees/export?event_id={$event->id}");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Ticket Number', $content);
        $this->assertStringContainsString('TCK-VIP-999', $content);
        $this->assertStringContainsString('Ama Serwaa', $content);
        $this->assertStringContainsString('VIP Access', $content);
        $this->assertStringNotContainsString('+233501234567', $content);
        $this->assertStringContainsString('***', $content);
    }

    public function test_admin_can_query_audit_logs(): void
    {
        $admin = $this->createAdminWithRole('Super Admin');

        AuditLog::create([
            'admin_id' => $admin->id,
            'action' => 'event.publish',
            'target_type' => Event::class,
            'target_id' => '123e4567-e89b-12d3-a456-426614174000',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit/10.0',
            'metadata' => ['event_name' => 'Accra Music Fest'],
        ]);

        $response = $this->actingAs($admin, 'web')
            ->withSession(['auth.mfa_verified' => true])
            ->getJson('/api/v1/admin/audit-logs?action=event.publish');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'event.publish')
            ->assertJsonPath('data.0.admin_name', $admin->name);
    }
}

