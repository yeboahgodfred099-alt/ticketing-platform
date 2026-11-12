<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileMoneyPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;
    private string $rawAccessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $event = Event::factory()->create([
            'status' => EventStatus::PUBLISHED,
            'currency' => 'GHS',
        ]);

        $this->rawAccessToken = bin2hex(random_bytes(32));

        $this->order = Order::factory()->create([
            'event_id' => $event->id,
            'customer_name' => 'Kofi Mensah',
            'customer_email' => 'kofi@example.com',
            'customer_phone' => '+233241234567',
            'subtotal_minor' => 10000,
            'total_amount_minor' => 10000, // GH₵ 100.00
            'currency' => 'GHS',
            'status' => OrderStatus::PENDING_PAYMENT,
            'expires_at' => Carbon::now()->addMinutes(15),
            'access_token_hash' => hash('sha256', $this->rawAccessToken),
        ]);
    }

    public function test_initiate_mobile_money_payment_handles_pay_offline(): void
    {
        Http::fake([
            'https://api.paystack.co/charge' => Http::response([
                'status' => true,
                'message' => 'Charge attempted',
                'data' => [
                    'reference' => 'PAY-TEST-REF',
                    'status' => 'pay_offline',
                    'display_text' => 'Please check your phone for a prompt to approve the transaction',
                    'id' => 12345678,
                ],
            ], 200),
        ]);

        $payload = [
            'order_number' => $this->order->order_number,
            'provider' => 'mtn',
            'phone' => '0241234567',
        ];

        $response = $this->postJson(
            route('api.v1.public.payments.initiate'),
            $payload,
            ['X-Order-Token' => $this->rawAccessToken]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.order_number', $this->order->order_number);
        $this->assertNotEmpty($response->json('data.reference'));

        $reference = $response->json('data.reference');

        // Check Payment row created in DB
        $payment = Payment::where('reference', $reference)->first();
        $this->assertNotNull($payment);
        $this->assertSame(PaymentStatus::PENDING, $payment->status);
        $this->assertSame('mtn', $payment->mobile_money_provider);
        $this->assertSame(10000, $payment->amount_minor);
        $this->assertSame('12345678', $payment->provider_transaction_id);
    }

    public function test_initiate_payment_requires_valid_access_token(): void
    {
        $payload = [
            'order_number' => $this->order->order_number,
            'provider' => 'mtn',
            'phone' => '0241234567',
        ];

        // Without token -> 403
        $response = $this->postJson(route('api.v1.public.payments.initiate'), $payload);
        $response->assertStatus(403);

        // With wrong token -> 403
        $responseWrong = $this->postJson(
            route('api.v1.public.payments.initiate'),
            $payload,
            ['X-Order-Token' => 'invalid-token-1234']
        );
        $responseWrong->assertStatus(403);
    }

    public function test_initiate_payment_handles_send_otp_flow(): void
    {
        Http::fake([
            'https://api.paystack.co/charge' => Http::response([
                'status' => true,
                'data' => [
                    'reference' => 'PAY-OTP-REF',
                    'status' => 'send_otp',
                    'display_text' => 'Please enter OTP sent to your phone',
                ],
            ], 200),
            'https://api.paystack.co/charge/submit_otp' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'pay_offline',
                    'display_text' => 'OTP verified. Please approve the prompt on your phone',
                ],
            ], 200),
        ]);

        $payload = [
            'order_number' => $this->order->order_number,
            'provider' => 'vod',
            'phone' => '0201234567',
        ];

        $response = $this->postJson(
            route('api.v1.public.payments.initiate'),
            $payload,
            ['X-Order-Token' => $this->rawAccessToken]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'requires_action');
        $response->assertJsonPath('data.action', 'send_otp');

        $reference = $response->json('data.reference');

        // Submit OTP
        $otpResponse = $this->postJson(
            route('api.v1.public.payments.submit-otp'),
            [
                'reference' => $reference,
                'otp' => '123456',
            ],
            ['X-Order-Token' => $this->rawAccessToken]
        );

        $otpResponse->assertStatus(200);
        $otpResponse->assertJsonPath('data.status', 'pending');
    }

    public function test_payment_status_polling_returns_sanitized_status(): void
    {
        $payment = Payment::create([
            'order_id' => $this->order->id,
            'reference' => 'PAY-TEST-POLL-123',
            'provider' => 'paystack',
            'channel' => 'mobile_money',
            'mobile_money_provider' => 'mtn',
            'amount_minor' => 10000,
            'fees_minor' => 0,
            'currency' => 'GHS',
            'status' => PaymentStatus::PENDING,
        ]);

        $response = $this->getJson(route('api.v1.public.payments.status', ['reference' => $payment->reference]));

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.reference', 'PAY-TEST-POLL-123');
        $response->assertJsonPath('data.amount_major', '100.00');
        $response->assertJsonPath('data.is_final', false);
    }

    public function test_cannot_initiate_payment_on_expired_order(): void
    {
        $this->order->update([
            'expires_at' => Carbon::now()->subMinutes(1),
        ]);

        $payload = [
            'order_number' => $this->order->order_number,
            'provider' => 'mtn',
            'phone' => '0241234567',
        ];

        $response = $this->postJson(
            route('api.v1.public.payments.initiate'),
            $payload,
            ['X-Order-Token' => $this->rawAccessToken]
        );

        $response->assertStatus(422);
    }
}

