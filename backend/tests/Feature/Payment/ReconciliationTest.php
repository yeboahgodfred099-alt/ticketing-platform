<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\TicketTypeStatus;
use App\Jobs\ReconcilePayments;
use App\Models\Event;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\TicketType;
use App\Services\Payment\OrderFulfillmentService;
use App\Services\Payment\PaystackAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciles_missed_successful_payment(): void
    {
        $event = Event::factory()->create(['status' => EventStatus::PUBLISHED]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'price_minor' => 5000,
            'quantity_total' => 50,
            'quantity_sold' => 0,
            'quantity_reserved' => 1,
            'status' => TicketTypeStatus::ACTIVE,
        ]);

        $order = Order::factory()->create([
            'event_id' => $event->id,
            'subtotal_minor' => 5000,
            'total_amount_minor' => 5000,
            'currency' => 'GHS',
            'status' => OrderStatus::PENDING_PAYMENT,
            'expires_at' => Carbon::now()->addMinutes(15),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_name_snapshot' => $ticketType->name,
            'quantity' => 1,
            'unit_price_minor' => 5000,
            'total_minor' => 5000,
            'currency' => 'GHS',
        ]);

        $reservation = InventoryReservation::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'status' => ReservationStatus::ACTIVE,
            'expires_at' => Carbon::now()->addMinutes(15),
        ]);

        // Created 10 minutes ago (beyond 5 min threshold)
        $payment = Payment::create([
            'order_id' => $order->id,
            'reference' => 'PAY-RECON-123',
            'provider' => 'paystack',
            'channel' => 'mobile_money',
            'mobile_money_provider' => 'mtn',
            'amount_minor' => 5000,
            'fees_minor' => 0,
            'currency' => 'GHS',
            'status' => PaymentStatus::PENDING,
            'created_at' => Carbon::now()->subMinutes(10),
            'updated_at' => Carbon::now()->subMinutes(10),
        ]);

        // Paystack reports success on verification
        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'id' => 776655,
                    'status' => 'success',
                    'reference' => 'PAY-RECON-123',
                    'amount' => 5000,
                    'currency' => 'GHS',
                    'channel' => 'mobile_money',
                    'fees' => 98,
                ],
            ], 200),
        ]);

        $adapter = app(PaystackAdapter::class);
        $fulfillment = app(OrderFulfillmentService::class);

        $job = new ReconcilePayments();
        $count = $job->handle($adapter, $fulfillment);

        $this->assertSame(1, $count);

        // Verify Payment is SUCCESS
        $payment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS, $payment->status);

        // Verify Order is PAID
        $order->refresh();
        $this->assertSame(OrderStatus::PAID, $order->status);

        // Verify inventory reservation converted
        $reservation->refresh();
        $this->assertSame(ReservationStatus::CONVERTED, $reservation->status);

        // Verify ticket quantity updated
        $ticketType->refresh();
        $this->assertSame(0, $ticketType->quantity_reserved);
        $this->assertSame(1, $ticketType->quantity_sold);
    }

    public function test_skips_recent_pending_payments_within_threshold_window(): void
    {
        $event = Event::factory()->create(['status' => EventStatus::PUBLISHED]);

        $order = Order::factory()->create([
            'event_id' => $event->id,
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);

        // Created 1 minute ago (within 5-min threshold)
        $payment = Payment::create([
            'order_id' => $order->id,
            'reference' => 'PAY-RECENT-999',
            'amount_minor' => 5000,
            'currency' => 'GHS',
            'status' => PaymentStatus::PENDING,
            'created_at' => Carbon::now()->subMinute(),
        ]);

        $adapter = app(PaystackAdapter::class);
        $fulfillment = app(OrderFulfillmentService::class);

        $job = new ReconcilePayments();
        $count = $job->handle($adapter, $fulfillment);

        $this->assertSame(0, $count);

        $payment->refresh();
        $this->assertSame(PaymentStatus::PENDING, $payment->status);
    }
}
