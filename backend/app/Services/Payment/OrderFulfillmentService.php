<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\InvalidCheckoutException;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TicketType;
use App\Services\Tickets\TicketIssuanceService;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderFulfillmentService
{
    public function __construct(
        protected PaystackAdapter $paystackAdapter,
        protected TicketIssuanceService $ticketIssuanceService
    ) {}

    /**
     * Atomically fulfill an order upon successful payment verification.
     *
     * @param string $reference
     * @param array<string, mixed>|null $webhookData Optional payload data if invoked from webhook
     * @return Order
     *
     * @throws InvalidCheckoutException
     * @throws Exception
     */
    public function fulfillOrder(string $reference, ?array $webhookData = null): Order
    {
        return DB::transaction(function () use ($reference, $webhookData) {
            // 1. Lock payment row
            /** @var Payment|null $payment */
            $payment = Payment::where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                throw new InvalidCheckoutException("Payment with reference {$reference} not found.");
            }

            // 2. Lock order row
            /** @var Order|null $order */
            $order = Order::where('id', $payment->order_id)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new InvalidCheckoutException("Order associated with payment {$reference} not found.");
            }

            // IDEMPOTENCY CHECK: If already fulfilled and paid, return order immediately
            if ($order->status === OrderStatus::PAID && $payment->status === PaymentStatus::SUCCESS) {
                Log::info("Order {$order->order_number} is already fulfilled. Skipping duplicate fulfillment.");
                return $order;
            }

            // 3. Verify transaction directly with Paystack API (defense in depth)
            $verificationResponse = $this->paystackAdapter->verifyTransaction($reference);
            $verifiedData = $verificationResponse['data'] ?? [];

            $verifiedStatus = strtolower($verifiedData['status'] ?? '');
            if ($verifiedStatus !== 'success') {
                $payment->update([
                    'status' => PaymentStatus::FAILED,
                    'failure_reason' => "Paystack verification status was '{$verifiedStatus}'",
                ]);
                throw new InvalidCheckoutException("Paystack transaction verification failed: status is {$verifiedStatus}.");
            }

            // 4. Verify amount, currency, and reference integrity
            $verifiedAmount = (int) ($verifiedData['amount'] ?? 0);
            $verifiedCurrency = strtoupper((string) ($verifiedData['currency'] ?? ''));

            if ($verifiedAmount !== $order->total_amount_minor) {
                $errorMsg = "Payment amount mismatch. Order expected {$order->total_amount_minor} pesewas, but Paystack verified {$verifiedAmount} pesewas.";
                $payment->update([
                    'status' => PaymentStatus::FAILED,
                    'failure_reason' => $errorMsg,
                ]);
                Log::critical("CRITICAL: {$errorMsg} [Reference: {$reference}, Order: {$order->order_number}]");
                throw new InvalidCheckoutException($errorMsg);
            }

            if ($verifiedCurrency !== $order->currency) {
                $errorMsg = "Payment currency mismatch. Order expected {$order->currency}, but Paystack verified {$verifiedCurrency}.";
                $payment->update([
                    'status' => PaymentStatus::FAILED,
                    'failure_reason' => $errorMsg,
                ]);
                Log::critical("CRITICAL: {$errorMsg} [Reference: {$reference}, Order: {$order->order_number}]");
                throw new InvalidCheckoutException($errorMsg);
            }

            // 5. Lock and convert inventory reservations in deterministic order
            $activeReservations = InventoryReservation::where('order_id', $order->id)
                ->where('status', ReservationStatus::ACTIVE)
                ->lockForUpdate()
                ->get();

            // Collect and sort ticket type IDs to prevent deadlocks
            $ticketTypeIds = $activeReservations->pluck('ticket_type_id')->unique()->sort()->values()->all();

            $now = Carbon::now();

            foreach ($ticketTypeIds as $ttId) {
                /** @var TicketType|null $ticketType */
                $ticketType = TicketType::where('id', $ttId)
                    ->lockForUpdate()
                    ->first();

                if (!$ticketType) {
                    continue;
                }

                $reservationsForType = $activeReservations->where('ticket_type_id', $ttId);
                $totalQtyForType = $reservationsForType->sum('quantity');

                // Convert reserved quantity to sold quantity
                $newReserved = max(0, $ticketType->quantity_reserved - $totalQtyForType);
                $newSold = $ticketType->quantity_sold + $totalQtyForType;

                $ticketType->update([
                    'quantity_reserved' => $newReserved,
                    'quantity_sold' => $newSold,
                ]);

                foreach ($reservationsForType as $res) {
                    $res->update([
                        'status' => ReservationStatus::CONVERTED,
                        'converted_at' => $now,
                    ]);
                }
            }

            // 6. Update Payment record
            $paystackFees = (int) ($verifiedData['fees'] ?? 0);
            $payment->update([
                'status' => PaymentStatus::SUCCESS,
                'fees_minor' => $paystackFees,
                'channel' => $verifiedData['channel'] ?? 'mobile_money',
                'provider_transaction_id' => isset($verifiedData['id']) ? (string) $verifiedData['id'] : $payment->provider_transaction_id,
                'verified_at' => $now,
            ]);

            // 7. Update Order record
            $order->update([
                'status' => OrderStatus::PAID,
                'paid_at' => $now,
            ]);

            // 8. Atomically issue tickets and queue delivery
            $this->ticketIssuanceService->issueTickets($order);

            Log::info("Order {$order->order_number} successfully fulfilled and tickets issued for payment reference {$reference}.");

            return $order->load(['items', 'event']);
        });
    }
}

