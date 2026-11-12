<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidCheckoutException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Support\PhoneNormalizer;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        protected PaystackAdapter $paystackAdapter
    ) {}

    /**
     * Initiate a Mobile Money payment charge for a pending order.
     *
     * @param Order $order
     * @param string $phone
     * @param string $momoProvider 'mtn' | 'vod' | 'atl'
     * @return array<string, mixed>
     *
     * @throws InvalidCheckoutException
     * @throws Exception
     */
    public function initiatePayment(Order $order, string $phone, string $momoProvider): array
    {
        $momoProvider = strtolower(trim($momoProvider));
        $validProviders = array_keys(config('paystack.providers', ['mtn' => 'MTN', 'vod' => 'Telecel', 'atl' => 'AT']));

        if (!in_array($momoProvider, $validProviders, true)) {
            throw new InvalidCheckoutException("Invalid Mobile Money provider '{$momoProvider}'. Supported: " . implode(', ', $validProviders));
        }

        // Validate Order eligibility
        if ($order->status !== OrderStatus::PENDING_PAYMENT) {
            throw new InvalidCheckoutException("Order {$order->order_number} is not awaiting payment (status: {$order->status->value}).");
        }

        if ($order->expires_at && Carbon::now()->isAfter($order->expires_at)) {
            throw new InvalidCheckoutException("The ticket reservation for order {$order->order_number} has expired. Please restart checkout.");
        }

        // Check if order already has a successful payment
        $hasPaidPayment = Payment::where('order_id', $order->id)
            ->where('status', PaymentStatus::SUCCESS)
            ->exists();

        if ($hasPaidPayment) {
            throw new InvalidCheckoutException("Order {$order->order_number} has already been paid.");
        }

        $normalizedPhone = PhoneNormalizer::normalize($phone);
        $reference = $this->generatePaymentReference($order->order_number);

        // 1. Create Payment record in DB in initiated state
        /** @var Payment $payment */
        $payment = Payment::create([
            'order_id' => $order->id,
            'reference' => $reference,
            'provider' => 'paystack',
            'channel' => 'mobile_money',
            'mobile_money_provider' => $momoProvider,
            'customer_phone' => $normalizedPhone,
            'amount_minor' => $order->total_amount_minor,
            'fees_minor' => 0, // Fees absorbed by organizer; customer pays zero fees
            'currency' => $order->currency,
            'status' => PaymentStatus::INITIATED,
        ]);

        // 2. Call Paystack Charge API
        try {
            $metadata = [
                'order_number' => $order->order_number,
                'order_id' => $order->id,
                'customer_name' => $order->customer_name,
                'event_id' => $order->event_id,
            ];

            $response = $this->paystackAdapter->chargeMobileMoney(
                $reference,
                $order->total_amount_minor,
                $order->customer_email,
                $normalizedPhone,
                $momoProvider,
                $metadata
            );

            return $this->processChargeResponse($payment, $response, $momoProvider);
        } catch (Exception $e) {
            $payment->update([
                'status' => PaymentStatus::FAILED,
                'failure_reason' => $e->getMessage(),
            ]);

            Log::error("Failed to initiate Mobile Money payment for order {$order->order_number}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Submit OTP continuation when provider requests OTP.
     *
     * @param string $reference
     * @param string $otp
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function submitOtp(string $reference, string $otp): array
    {
        /** @var Payment|null $payment */
        $payment = Payment::where('reference', $reference)->first();

        if (!$payment) {
            throw new InvalidCheckoutException("Payment transaction not found.");
        }

        if ($payment->status !== PaymentStatus::REQUIRES_ACTION) {
            throw new InvalidCheckoutException("Payment is not awaiting action (status: {$payment->status->value}).");
        }

        try {
            $response = $this->paystackAdapter->submitOtp($otp, $reference);
            return $this->processChargeResponse($payment, $response, $payment->mobile_money_provider ?? 'mtn');
        } catch (Exception $e) {
            Log::error("OTP submission failed for reference {$reference}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Get clean status response for frontend polling.
     *
     * @param string $reference
     * @return array<string, mixed>
     */
    public function getPaymentStatus(string $reference): array
    {
        /** @var Payment|null $payment */
        $payment = Payment::where('reference', $reference)
            ->with(['order.event'])
            ->first();

        if (!$payment) {
            return [
                'found' => false,
                'message' => 'Payment reference not found.',
            ];
        }

        $order = $payment->order;
        $orderExpired = $order->expires_at && Carbon::now()->isAfter($order->expires_at);

        $statusValue = $payment->status->value;
        if ($payment->status->isPending() && $orderExpired) {
            $statusValue = 'expired';
        }

        return [
            'found' => true,
            'reference' => $payment->reference,
            'order_number' => $order->order_number,
            'status' => $statusValue,
            'amount_minor' => $payment->amount_minor,
            'amount_major' => number_format($payment->amount_minor / 100, 2, '.', ''),
            'currency' => $payment->currency,
            'provider' => $payment->mobile_money_provider,
            'display_text' => $this->getDisplayTextForStatus($payment, $statusValue),
            'is_final' => in_array($statusValue, ['success', 'failed', 'expired', 'abandoned'], true),
            'expires_at' => $order->expires_at?->toIso8601String(),
        ];
    }

    /**
     * Process and record the result from Paystack charge or submit_otp response.
     */
    protected function processChargeResponse(Payment $payment, array $response, string $provider): array
    {
        $data = $response['data'] ?? [];
        $chargeStatus = strtolower($data['status'] ?? 'pending');
        $displayText = $data['display_text'] ?? null;
        $transactionId = isset($data['id']) ? (string) $data['id'] : null;

        // Scrub sensitive fields from provider response before saving
        $sanitizedData = $this->sanitizeProviderData($data);

        $updates = [
            'provider_response_sanitized' => $sanitizedData,
        ];
        if ($transactionId) {
            $updates['provider_transaction_id'] = $transactionId;
        }

        switch ($chargeStatus) {
            case 'pay_offline':
            case 'pending':
                $updates['status'] = PaymentStatus::PENDING;
                $payment->update($updates);

                $instruction = $displayText ?: $this->getDefaultInstruction($provider);
                return [
                    'status' => 'pending',
                    'reference' => $payment->reference,
                    'order_number' => $payment->order->order_number,
                    'display_text' => $instruction,
                    'timeout_seconds' => config('ticketing.payment_timeout_seconds', 180),
                ];

            case 'send_otp':
                $updates['status'] = PaymentStatus::REQUIRES_ACTION;
                $payment->update($updates);

                return [
                    'status' => 'requires_action',
                    'action' => 'send_otp',
                    'reference' => $payment->reference,
                    'order_number' => $payment->order->order_number,
                    'display_text' => $displayText ?: 'Please enter the OTP sent to your phone to authorize this payment.',
                ];

            case 'success':
                $updates['status'] = PaymentStatus::SUCCESS;
                $updates['verified_at'] = Carbon::now();
                $payment->update($updates);

                return [
                    'status' => 'success',
                    'reference' => $payment->reference,
                    'order_number' => $payment->order->order_number,
                    'display_text' => 'Payment approved successfully!',
                ];

            case 'failed':
            default:
                $updates['status'] = PaymentStatus::FAILED;
                $updates['failure_reason'] = $data['message'] ?? 'Transaction was declined by the provider.';
                $payment->update($updates);

                return [
                    'status' => 'failed',
                    'reference' => $payment->reference,
                    'order_number' => $payment->order->order_number,
                    'display_text' => $updates['failure_reason'],
                ];
        }
    }

    /**
     * Provide network-specific prompt instructions for Ghana MoMo.
     */
    protected function getDefaultInstruction(string $provider): string
    {
        return match (strtolower($provider)) {
            'mtn' => "A prompt has been sent to your MTN phone. Enter your MM PIN to approve. (If the prompt does not appear, dial *170# -> My Wallet -> Approvals).",
            'vod' => "Approve the prompt on your Telecel Cash phone. (If required, generate a voucher via *110# before proceeding).",
            'atl' => "Enter your AT Money PIN when prompted on your phone to complete payment.",
            default => "Please check your mobile phone and approve the payment prompt.",
        };
    }

    /**
     * Build appropriate status display text.
     */
    protected function getDisplayTextForStatus(Payment $payment, string $status): string
    {
        return match ($status) {
            'success' => 'Payment confirmed and verified. Your tickets are issued!',
            'pending' => $this->getDefaultInstruction($payment->mobile_money_provider ?? 'mtn'),
            'requires_action' => 'Action required: Please enter the OTP sent to your phone.',
            'failed' => $payment->failure_reason ?: 'Payment failed or was cancelled.',
            'expired' => 'The reservation hold for this payment has expired.',
            default => 'Processing payment...',
        };
    }

    /**
     * Remove any accidental customer secrets or headers from logged provider payloads.
     */
    protected function sanitizeProviderData(array $data): array
    {
        $sensitiveKeys = ['pin', 'password', 'otp', 'token', 'secret', 'authorization'];
        $sanitized = [];

        foreach ($data as $key => $val) {
            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($val)) {
                $sanitized[$key] = $this->sanitizeProviderData($val);
            } else {
                $sanitized[$key] = $val;
            }
        }

        return $sanitized;
    }

    /**
     * Generate unique payment reference: PAY-ORD-XXXX-XXXX-YYYY
     */
    protected function generatePaymentReference(string $orderNumber): string
    {
        do {
            $suffix = strtoupper(Str::random(4));
            $reference = "PAY-{$orderNumber}-{$suffix}";
        } while (Payment::where('reference', $reference)->exists());

        return $reference;
    }
}

