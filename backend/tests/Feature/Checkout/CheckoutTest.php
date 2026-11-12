<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\TicketTypeStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private TicketType $regularTicket;
    private TicketType $vipTicket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create([
            'status' => EventStatus::PUBLISHED,
            'currency' => 'GHS',
        ]);

        $this->regularTicket = TicketType::factory()->create([
            'event_id' => $this->event->id,
            'name' => 'Regular Pass',
            'price_minor' => 5000, // 50.00 GHS
            'quantity_total' => 100,
            'quantity_sold' => 0,
            'quantity_reserved' => 0,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'status' => TicketTypeStatus::ACTIVE,
        ]);

        $this->vipTicket = TicketType::factory()->create([
            'event_id' => $this->event->id,
            'name' => 'VIP Pass',
            'price_minor' => 15000, // 150.00 GHS
            'quantity_total' => 20,
            'quantity_sold' => 0,
            'quantity_reserved' => 0,
            'min_per_order' => 1,
            'max_per_order' => 5,
            'status' => TicketTypeStatus::ACTIVE,
        ]);
    }

    public function test_guest_can_checkout_and_reserve_inventory(): void
    {
        $payload = [
            'event_slug' => $this->event->slug,
            'customer_name' => 'Kwame Mensah',
            'customer_email' => 'kwame@example.com',
            'customer_phone' => '0241234567',
            'items' => [
                ['ticket_type_id' => $this->regularTicket->id, 'quantity' => 2],
                ['ticket_type_id' => $this->vipTicket->id, 'quantity' => 1],
            ],
        ];

        $response = $this->postJson(route('api.v1.public.checkout'), $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'order_number',
                'status',
                'customer_name',
                'customer_email',
                'customer_phone',
                'subtotal_minor',
                'total_amount_minor',
                'currency',
                'expires_at',
                'access_token',
                'items',
            ],
        ]);

        $orderNumber = $response->json('data.order_number');
        $rawAccessToken = $response->json('data.access_token');

        $this->assertNotNull($rawAccessToken);
        $this->assertSame(64, strlen($rawAccessToken)); // 32 bytes hex

        // Expected authoritative total: (2 * 5000) + (1 * 15000) = 25000 pesewas (GH₵ 250.00)
        $this->assertSame(25000, $response->json('data.subtotal_minor'));
        $this->assertSame(25000, $response->json('data.total_amount_minor'));
        $this->assertSame('+233241234567', $response->json('data.customer_phone'));

        // Verify Database state
        $order = Order::where('order_number', $orderNumber)->first();
        $this->assertNotNull($order);
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->status);
        $this->assertSame(hash('sha256', $rawAccessToken), $order->access_token_hash);

        // Verify inventory reservations
        $this->regularTicket->refresh();
        $this->assertSame(2, $this->regularTicket->quantity_reserved);

        $this->vipTicket->refresh();
        $this->assertSame(1, $this->vipTicket->quantity_reserved);

        $this->assertDatabaseHas('inventory_reservations', [
            'order_id' => $order->id,
            'ticket_type_id' => $this->regularTicket->id,
            'quantity' => 2,
            'status' => ReservationStatus::ACTIVE->value,
        ]);
    }

    public function test_price_tampering_is_impossible_authoritative_calculation_used(): void
    {
        $payload = [
            'event_slug' => $this->event->slug,
            'customer_name' => 'Akua Poku',
            'customer_email' => 'akua@example.com',
            'customer_phone' => '0551234567',
            'items' => [
                ['ticket_type_id' => $this->regularTicket->id, 'quantity' => 1],
            ],
            // Malicious client fields that should be completely ignored
            'subtotal_minor' => 0,
            'total_amount_minor' => 0,
            'price_minor' => 1,
            'discount_minor' => 5000,
        ];

        $response = $this->postJson(route('api.v1.public.checkout'), $payload);

        $response->assertStatus(201);
        // Authoritative price for 1 regular ticket is 5000 pesewas
        $this->assertSame(5000, $response->json('data.total_amount_minor'));
        $this->assertSame(5000, $response->json('data.subtotal_minor'));
    }

    public function test_checkout_fails_when_requested_quantity_exceeds_available(): void
    {
        // Only 20 VIP tickets available
        $payload = [
            'event_slug' => $this->event->slug,
            'customer_name' => 'Kojo Antwi',
            'customer_email' => 'kojo@example.com',
            'customer_phone' => '0241234567',
            'items' => [
                ['ticket_type_id' => $this->vipTicket->id, 'quantity' => 25],
            ],
        ];

        $response = $this->postJson(route('api.v1.public.checkout'), $payload);

        $response->assertStatus(422);
        $this->regularTicket->refresh();
        $this->assertSame(0, $this->regularTicket->quantity_reserved);
    }

    public function test_checkout_fails_on_unpublished_event(): void
    {
        $draftEvent = Event::factory()->create([
            'status' => EventStatus::DRAFT,
        ]);

        $ticket = TicketType::factory()->create([
            'event_id' => $draftEvent->id,
            'status' => TicketTypeStatus::ACTIVE,
        ]);

        $payload = [
            'event_slug' => $draftEvent->slug,
            'customer_name' => 'Ama Serwaa',
            'customer_email' => 'ama@example.com',
            'customer_phone' => '0241234567',
            'items' => [
                ['ticket_type_id' => $ticket->id, 'quantity' => 1],
            ],
        ];

        $response = $this->postJson(route('api.v1.public.checkout'), $payload);

        $response->assertStatus(422);
    }

    public function test_customer_can_retrieve_order_only_with_valid_access_token(): void
    {
        $payload = [
            'event_slug' => $this->event->slug,
            'customer_name' => 'Kofi Annan',
            'customer_email' => 'kofi@example.com',
            'customer_phone' => '0241234567',
            'items' => [
                ['ticket_type_id' => $this->regularTicket->id, 'quantity' => 1],
            ],
        ];

        $checkoutResponse = $this->postJson(route('api.v1.public.checkout'), $payload);
        $checkoutResponse->assertStatus(201);

        $orderNumber = $checkoutResponse->json('data.order_number');
        $rawAccessToken = $checkoutResponse->json('data.access_token');

        // 1. Retrieval with valid token via header
        $retrievalResponse = $this->getJson(
            route('api.v1.public.orders.show', ['orderNumber' => $orderNumber]),
            ['X-Order-Token' => $rawAccessToken]
        );
        $retrievalResponse->assertStatus(200);
        $retrievalResponse->assertJsonPath('data.order_number', $orderNumber);

        // 2. Retrieval with valid token via query string
        $queryResponse = $this->getJson(
            route('api.v1.public.orders.show', ['orderNumber' => $orderNumber, 'token' => $rawAccessToken])
        );
        $queryResponse->assertStatus(200);

        // 3. Retrieval without token fails (403 Forbidden)
        $noTokenResponse = $this->getJson(
            route('api.v1.public.orders.show', ['orderNumber' => $orderNumber])
        );
        $noTokenResponse->assertStatus(403);

        // 4. Retrieval with wrong token fails (403 Forbidden)
        $wrongTokenResponse = $this->getJson(
            route('api.v1.public.orders.show', ['orderNumber' => $orderNumber]),
            ['X-Order-Token' => 'wrong-token-abc-123']
        );
        $wrongTokenResponse->assertStatus(403);
    }
}

