<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\Tickets\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    private Admin $staff;
    private Event $event;
    private Ticket $ticket;
    private string $rawQrToken;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create permission & role for Gate Staff
        $scanPermission = Permission::firstOrCreate(
            ['name' => 'check_in.scan'],
            ['description' => 'Scan tickets and perform check-ins']
        );

        $role = Role::firstOrCreate(
            ['name' => 'Gate Staff'],
            ['description' => 'Gate staff scanner role']
        );
        $role->permissions()->sync([$scanPermission->id]);

        $this->staff = Admin::factory()->create([
            'is_active' => true,
        ]);
        $this->staff->roles()->sync([$role->id]);

        // 2. Create Event & Ticket
        $this->event = Event::factory()->create([
            'status' => EventStatus::PUBLISHED,
        ]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $this->event->id,
            'name' => 'VIP Access',
        ]);

        $order = Order::factory()->create([
            'event_id' => $this->event->id,
            'status' => OrderStatus::PAID,
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_name_snapshot' => $ticketType->name,
            'quantity' => 1,
            'unit_price_minor' => 10000,
            'total_minor' => 10000,
            'currency' => 'GHS',
        ]);

        $qrService = app(QrCodeService::class);
        $tokenData = $qrService->generateToken('test-ticket-uuid', 'TCK-TEST-001');
        $this->rawQrToken = $tokenData['raw'];

        $this->ticket = Ticket::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'order_item_id' => $orderItem->id,
            'event_id' => $this->event->id,
            'ticket_number' => 'TCK-TEST-001',
            'qr_token_hash' => $tokenData['hash'],
            'holder_name' => 'Kwame Nkrumah',
            'holder_email' => 'kwame@example.com',
            'status' => TicketStatus::ACTIVE,
            'issued_at' => Carbon::now(),
        ]);
    }

    public function test_staff_can_successfully_check_in_valid_ticket_via_raw_qr_token(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson(route('api.v1.staff.check-in'), [
            'event_id' => $this->event->id,
            'token' => $this->rawQrToken,
            'gate_identifier' => 'Gate A - North',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('outcome', 'valid');
        $response->assertJsonPath('data.holder_name', 'Kwame Nkrumah');
        $response->assertJsonPath('data.ticket_number', 'TCK-TEST-001');

        // Check ticket status updated to CHECKED_IN
        $this->ticket->refresh();
        $this->assertSame(TicketStatus::CHECKED_IN, $this->ticket->status);
        $this->assertNotNull($this->ticket->used_at);

        // Check CheckIn record created in DB
        $this->assertDatabaseHas('check_ins', [
            'ticket_id' => $this->ticket->id,
            'admin_id' => $this->staff->id,
            'gate_identifier' => 'Gate A - North',
        ]);
    }

    public function test_staff_can_check_in_using_ticket_number_fallback(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson(route('api.v1.staff.check-in'), [
            'event_id' => $this->event->id,
            'token' => 'TCK-TEST-001',
            'gate_identifier' => 'Turnstile 2',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('outcome', 'valid');
        $this->ticket->refresh();
        $this->assertSame(TicketStatus::CHECKED_IN, $this->ticket->status);
    }

    public function test_double_scan_is_rejected_with_already_used_outcome(): void
    {
        Sanctum::actingAs($this->staff);

        // First scan
        $firstResponse = $this->postJson(route('api.v1.staff.check-in'), [
            'event_id' => $this->event->id,
            'token' => $this->rawQrToken,
            'gate_identifier' => 'Gate 1',
        ]);
        $firstResponse->assertJsonPath('outcome', 'valid');

        // Immediate duplicate scan
        $secondResponse = $this->postJson(route('api.v1.staff.check-in'), [
            'event_id' => $this->event->id,
            'token' => $this->rawQrToken,
            'gate_identifier' => 'Gate 2',
        ]);

        $secondResponse->assertStatus(200);
        $secondResponse->assertJsonPath('outcome', 'already_used');
        $this->assertStringContainsString('ALREADY USED', $secondResponse->json('message'));

        // Check only 1 check-in record exists
        $this->assertSame(1, CheckIn::where('ticket_id', $this->ticket->id)->count());
    }

    public function test_ticket_for_wrong_event_is_rejected(): void
    {
        Sanctum::actingAs($this->staff);

        $anotherEvent = Event::factory()->create([
            'name' => 'Kumasi Festival',
            'status' => EventStatus::PUBLISHED,
        ]);

        $response = $this->postJson(route('api.v1.staff.check-in'), [
            'event_id' => $anotherEvent->id,
            'token' => $this->rawQrToken,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('outcome', 'wrong_event');
    }

    public function test_invalid_token_is_rejected(): void
    {
        Sanctum::actingAs($this->staff);

        $fakeToken = bin2hex(random_bytes(32));

        $response = $this->postJson(route('api.v1.staff.check-in'), [
            'event_id' => $this->event->id,
            'token' => $fakeToken,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('outcome', 'invalid');
    }

    public function test_cancelled_ticket_is_rejected(): void
    {
        Sanctum::actingAs($this->staff);

        $this->ticket->update(['status' => TicketStatus::CANCELLED]);

        $response = $this->postJson(route('api.v1.staff.check-in'), [
            'event_id' => $this->event->id,
            'token' => $this->rawQrToken,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('outcome', 'cancelled');
    }

    public function test_unauthenticated_request_is_rejected_with_401(): void
    {
        $response = $this->postJson(route('api.v1.staff.check-in'), [
            'event_id' => $this->event->id,
            'token' => $this->rawQrToken,
        ]);

        $response->assertStatus(401);
    }

    public function test_staff_without_permission_is_rejected_with_403(): void
    {
        $unauthorizedAdmin = Admin::factory()->create(['is_active' => true]);
        Sanctum::actingAs($unauthorizedAdmin);

        $response = $this->postJson(route('api.v1.staff.check-in'), [
            'event_id' => $this->event->id,
            'token' => $this->rawQrToken,
        ]);

        $response->assertStatus(403);
    }

    public function test_stats_endpoint_returns_real_time_gate_counters(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->getJson(route('api.v1.staff.events.stats', ['event' => $this->event->id]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'event' => ['id', 'name'],
                'stats' => [
                    'total_sold',
                    'total_checked_in',
                    'remaining_to_arrive',
                    'check_in_percentage',
                    'recent_check_ins',
                ],
            ],
        ]);
    }
}

