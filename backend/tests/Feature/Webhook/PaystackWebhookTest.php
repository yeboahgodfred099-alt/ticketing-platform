<?php

declare(strict_types=1);

namespace Tests\Feature\Webhook;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\TicketTypeStatus;
use App\Enums\WebhookStatus;
use App\Models\Event;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\TicketType;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'sk_test_mock_webhook_secret_12345';
    private Order $order;
    private Payment $payment;
    private TicketType $ticketType;
    private InventoryReservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('paystack.webhook_secret', $this->webhookSecret);
        Config::set('paystack.secret_key', $this->webhookSecret);

        $event = Event::factory()->create(['status' => EventStatus::PUBLISHED]);

        $this->ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'price_minor' => 10000,
            'quantity_total' => 100,
            'quantity_sold' => 0,
            'quantity_reserved' => 2,
            'status' => TicketTypeStatus::ACTIVE,
        ]);

        $this->order = Order::factory()->create([
            'event_id' => $event->id,
            'subtotal_minor' => 20000,
            'total_amount_minor' => 20000, // GH₵ 200.00
            'currency' => 'GHS',
            'status' => OrderStatus::PENDING_PAYMENT,
            'expires_at' => Carbon::now()->addMinutes(15),
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

        $this->reservation = InventoryReservation::create([
            'order_id' => $this->order->id,
            'ticket_type_id' => $this->ticketType->id,
            'quantity' => 2,
            'status' => ReservationStatus::ACTIVE,
            'expires_at' => Carbon::now()->addMinutes(15),
        ]);

        $this->payment = Payment::create([
            'order_id' => $this->order->id,
            'reference' => 'PAY-TEST-WH-1234',
            'provider' => 'paystack',
            'channel' => 'mobile_money',
            'mobile_money_provider' => 'mtn',
            'amount_minor' => 20000,
            'fees_minor' => 0,
            'currency' => 'GHS',
            'status' => PaymentStatus::PENDING,
        ]);
    }

    public function test_valid_webhook_signature_is_accepted_and_fulfills_order(): void
    {
        // Mock Paystack verification call
        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'id' => 998877,
                    'status' => 'success',
                    'reference' => $this->payment->reference,
                    'amount' => 20000,
                    'currency' => 'GHS',
                    'channel' => 'mobile_money',
                    'fees' => 390,
                ],
            ], 200),
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'id' => 998877,
                'reference' => $this->payment->reference,
                'amount' => 20000,
                'currency' => 'GHS',
                'status' => 'success',
                'paid_at' => Carbon::now()->toIso8601String(),
            ],
        ];

        $rawBody = json_encode($payload);
        $signature = hash_hmac('sha512', $rawBody, $this->webhookSecret);

        $response = $this->call(
            'POST',
            route('api.v1.webhooks.paystack'),
            [],
            [],
            [],
            [
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $rawBody
        );

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        // Check WebhookEvent recorded and marked PROCESSED
        $webhook = WebhookEvent::where('event_identifier', 'paystack:charge.success:998877')->first();
        $this->assertNotNull($webhook);
        $this->assertSame(WebhookStatus::PROCESSED, $webhook->processing_status);

        // Check Payment transitioned to SUCCESS
        $this->payment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS, $this->payment->status);
        $this->assertSame('998877', $this->payment->provider_transaction_id);
        $this->assertSame(390, $this->payment->fees_minor);

        // Check Order transitioned to PAID
        $this->order->refresh();
        $this->assertSame(OrderStatus::PAID, $this->order->status);
        $this->assertNotNull($this->order->paid_at);

        // Check Inventory reservation converted
        $this->reservation->refresh();
        $this->assertSame(ReservationStatus::CONVERTED, $this->reservation->status);
        $this->assertNotNull($this->reservation->converted_at);

        // Check TicketType counts: quantity_reserved decremented, quantity_sold incremented
        $this->ticketType->refresh();
        $this->assertSame(0, $this->ticketType->quantity_reserved);
        $this->assertSame(2, $this->ticketType->quantity_sold);
    }

    public function test_invalid_signature_is_rejected_with_401(): void
    {
        $payload = [
            'event' => 'charge.success',
            'data' => ['id' => 1234, 'reference' => 'PAY-FAKE'],
        ];

        $rawBody = json_encode($payload);
        $invalidSignature = 'bad_signature_hash_value_12345';

        $response = $this->call(
            'POST',
            route('api.v1.webhooks.paystack'),
            [],
            [],
            [],
            [
                'HTTP_X_PAYSTACK_SIGNATURE' => $invalidSignature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $rawBody
        );

        $response->assertStatus(401);
        $this->assertDatabaseMissing('webhook_events', [
            'event_identifier' => 'paystack:charge.success:1234',
        ]);
    }

    public function test_duplicate_webhook_is_deduplicated_and_not_reprocessed(): void
    {
        // First record an already-processed webhook
        WebhookEvent::create([
            'provider' => 'paystack',
            'event_identifier' => 'paystack:charge.success:998877',
            'event_type' => 'charge.success',
            'payment_id' => $this->payment->id,
            'payload_hash' => 'dummy_hash',
            'processing_status' => WebhookStatus::PROCESSED,
            'received_at' => Carbon::now()->subMinutes(2),
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'id' => 998877,
                'reference' => $this->payment->reference,
            ],
        ];

        $rawBody = json_encode($payload);
        $signature = hash_hmac('sha512', $rawBody, $this->webhookSecret);

        $response = $this->call(
            'POST',
            route('api.v1.webhooks.paystack'),
            [],
            [],
            [],
            ['HTTP_X_PAYSTACK_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $rawBody
        );

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Webhook already received.');

        // Verify only 1 webhook event row exists in DB
        $this->assertSame(1, WebhookEvent::where('event_identifier', 'paystack:charge.success:998877')->count());
    }

    public function test_amount_mismatch_fails_verification_and_flags_payment(): void
    {
        // Webhook claims success but amount is less than expected (e.g. 10000 instead of 20000)
        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'id' => 554433,
                    'status' => 'success',
                    'reference' => $this->payment->reference,
                    'amount' => 10000, // Mismatch!
                    'currency' => 'GHS',
                ],
            ], 200),
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'id' => 554433,
                'reference' => $this->payment->reference,
                'amount' => 10000,
                'currency' => 'GHS',
                'status' => 'success',
            ],
        ];

        $rawBody = json_encode($payload);
        $signature = hash_hmac('sha512', $rawBody, $this->webhookSecret);

        $response = $this->call(
            'POST',
            route('api.v1.webhooks.paystack'),
            [],
            [],
            [],
            ['HTTP_X_PAYSTACK_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $rawBody
        );

        $response->assertStatus(200);

        // Payment should be marked FAILED due to amount mismatch
        $this->payment->refresh();
        $this->assertSame(PaymentStatus::FAILED, $this->payment->status);
        $this->assertStringContainsString('amount mismatch', (string) $this->payment->failure_reason);

        // Order remains PENDING_PAYMENT
        $this->order->refresh();
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $this->order->status);
    }
}
