<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Mail\TicketReceiptMail;
use App\Models\NotificationLog;
use App\Models\Order;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTicketEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public string $orderId
    ) {}

    public function handle(): void
    {
        /** @var Order|null $order */
        $order = Order::with(['event', 'items', 'tickets'])->find($this->orderId);

        if (!$order || empty($order->customer_email)) {
            Log::warning("Cannot send ticket email: order {$this->orderId} not found or missing email.");
            return;
        }

        $maskedEmail = $this->maskEmail($order->customer_email);

        /** @var NotificationLog $log */
        $log = NotificationLog::create([
            'order_id' => $order->id,
            'channel' => 'email',
            'recipient_masked' => $maskedEmail,
            'template_key' => 'ticket_receipt',
            'status' => NotificationStatus::QUEUED,
            'attempt_count' => 1,
        ]);

        try {
            Mail::to($order->customer_email)->send(new TicketReceiptMail($order));

            $log->update([
                'status' => NotificationStatus::SENT,
            ]);

            Log::info("Ticket receipt email sent to {$maskedEmail} for order {$order->order_number}.");
        } catch (Exception $e) {
            $log->update([
                'status' => NotificationStatus::FAILED,
            ]);

            Log::error("Failed to send ticket email to {$maskedEmail}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Mask customer email to protect PII in database logs (e.g. kwame@example.com -> k***@example.com).
     */
    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }

        $name = $parts[0];
        $domain = $parts[1];

        $maskedName = strlen($name) <= 2
            ? substr($name, 0, 1) . '***'
            : substr($name, 0, 1) . '***' . substr($name, -1);

        return "{$maskedName}@{$domain}";
    }
}
