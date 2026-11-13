<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\OrderFulfillmentService;
use App\Services\Payment\PaystackAdapter;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ReconcilePayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        PaystackAdapter $paystackAdapter,
        OrderFulfillmentService $fulfillmentService
    ): int {
        $thresholdMinutes = (int) config('ticketing.reconciliation_pending_threshold_minutes', 5);
        $thresholdTime = Carbon::now()->subMinutes($thresholdMinutes);

        // Fetch payments that remain pending beyond the threshold window
        $pendingPayments = Payment::whereIn('status', [PaymentStatus::INITIATED, PaymentStatus::PENDING])
            ->where('created_at', '<=', $thresholdTime)
            ->with('order')
            ->get();

        if ($pendingPayments->isEmpty()) {
            return 0;
        }

        $reconciledCount = 0;

        foreach ($pendingPayments as $payment) {
            try {
                $verification = $paystackAdapter->verifyTransaction($payment->reference);
                $data = $verification['data'] ?? [];
                $remoteStatus = strtolower($data['status'] ?? '');

                if ($remoteStatus === 'success') {
                    $fulfillmentService->fulfillOrder($payment->reference, $data);
                    Log::info("Reconciliation: Payment {$payment->reference} confirmed successful and fulfilled.");
                    $reconciledCount++;
                } elseif (in_array($remoteStatus, ['failed', 'abandoned'], true)) {
                    $payment->update([
                        'status' => $remoteStatus === 'failed' ? PaymentStatus::FAILED : PaymentStatus::ABANDONED,
                        'failure_reason' => $data['gateway_response'] ?? "Transaction status: {$remoteStatus}",
                    ]);
                    Log::info("Reconciliation: Payment {$payment->reference} marked as {$remoteStatus}.");
                    $reconciledCount++;
                }
            } catch (Exception $e) {
                Log::warning("Reconciliation check failed for payment {$payment->reference}: {$e->getMessage()}");
            }
        }

        return $reconciledCount;
    }
}

