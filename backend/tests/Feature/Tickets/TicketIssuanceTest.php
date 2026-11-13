<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Jobs\SendTicketEmail;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\Tickets\QrCodeService;
use App\Services\Tickets\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketIssuanceTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private Order $order;
    private TicketType $ticketType;
    private string $rawAccessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create([
            'status' => EventStatus::PUBLISHED,
        ]);

        $this->ticketType = TicketType::factory()->create([
            'event_id' => $this->event->id,
            'name' => 'VIP Admission',
            'price_minor' => 10000,
            'quantity_total' => 50,
            'status' => 'active',
        ]);

        $this->rawAccessToken = bin2hex(random_bytes(32));

        $this->order = Order::factory()->create([
            'event_id' => $this->event->id,
            'customer_name' => 'Ama Serwaa',
            'customer_email' => 'ama@example.com',
            'customer_phone' => '+233241234567',
            'subtotal_minor' => 20000,
            'total_amount_minor' => 20000,
            'currency' => 'GHS',
            'status' => OrderStatus::PAID,
            'paid_at' => Carbon::now(),
            'access_token_hash' => hash('sha256', $this->rawAccessToken),
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'ticket_type_id' => $this->ticketType->id,
            'ticket_name_snapshot' => $this->ticketType->name,
            'quantity' => 2,
            'unit_price_minor' => 10000,
            'total_minor' => 20000,
            'currency' => 'GHS',
        ]);
    }

    public function test_issues_correct_number_of_tickets_with_unique_hashes(): void
    {
        Queue::fake();

        $service = app(TicketIssuanceService::class);
        $tickets = $service->issueTickets($this->order);

        $this->assertCount(2, $tickets);

        $ticket1 = $tickets[0];
        $ticket2 = $tickets[1];

        // Format checks
        $this->assertStringStartsWith("TCK-{$this->order->order_number}-001", $ticket1->ticket_number);
        $this->assertStringStartsWith("TCK-{$this->order->order_number}-002", $ticket2->ticket_number);

        // QR hashes must be unique and non-null
        $this->assertNotNull($ticket1->qr_token_hash);
        $this->assertNotNull($ticket2->qr_token_hash);
        $this->assertNotSame($ticket1->qr_token_hash, $ticket2->qr_token_hash);

        // Ticket status
        $this->assertSame(TicketStatus::ACTIVE, $ticket1->status);
        $this->assertSame('Ama Serwaa', $ticket1->holder_name);

        // SendTicketEmail job dispatched
        Queue::assertPushed(SendTicketEmail::class, function ($job) {
            return $job->orderId === $this->order->id;
        });
    }

    public function test_ticket_issuance_is_idempotent(): void
    {
        Queue::fake();

        $service = app(TicketIssuanceService::class);

        // First issuance
        $firstBatch = $service->issueTickets($this->order);
        $this->assertCount(2, $firstBatch);

        // Second issuance attempt
        $secondBatch = $service->issueTickets($this->order);
        $this->assertCount(2, $secondBatch);

        // Total tickets in DB remains exactly 2
        $this->assertSame(2, Ticket::where('event_id', $this->event->id)->count());
    }

    public function test_customer_can_retrieve_tickets_with_access_token(): void
    {
        $service = app(TicketIssuanceService::class);
        $service->issueTickets($this->order);

        $response = $this->getJson(route('api.v1.public.tickets.show', [
            'accessToken' => $this->rawAccessToken,
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'order' => [
                    'order_number',
                    'customer_name',
                    'total_amount_minor',
                ],
                'event' => [
                    'name',
                    'venue_name',
                    'starts_at',
                ],
                'tickets' => [
                    '*' => [
                        'id',
                        'ticket_number',
                        'category_name',
                        'holder_name',
                        'status',
                        'qr_code_data_uri',
                        'raw_qr_token',
                    ],
                ],
            ],
        ]);

        $this->assertCount(2, $response->json('data.tickets'));
        $firstTicket = $response->json('data.tickets.0');
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $firstTicket['qr_code_data_uri']);
        $this->assertSame(64, strlen($firstTicket['raw_qr_token']));
    }

    public function test_ticket_retrieval_fails_for_unpaid_order(): void
    {
        $this->order->update(['status' => OrderStatus::PENDING_PAYMENT]);

        $response = $this->getJson(route('api.v1.public.tickets.show', [
            'accessToken' => $this->rawAccessToken,
        ]));

        $response->assertStatus(403);
    }
}
