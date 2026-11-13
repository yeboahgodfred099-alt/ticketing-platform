<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\WebhookStatus;
use App\Models\WebhookEvent;
use App\Services\Payment\OrderFulfillmentService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessPaystackWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * @param string $webhookEventId
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $webhookEventId,
        public array $payload
    ) {}

    public function handle(OrderFulfillmentService $fulfillmentService): void
    {
        /** @var WebhookEvent|null $webhook */
        $webhook = WebhookEvent::find($this->webhookEventId);
        if (!$webhook) {
            return;
        }

        // If already processed, skip
        if ($webhook->processing_status === WebhookStatus::PROCESSED) {
            return;
        }

        $webhook->update([
            'processing_status' => WebhookStatus::PROCESSING,
        ]);

        $eventType = $this->payload['event'] ?? '';
        $data = $this->payload['data'] ?? [];

        try {
            switch ($eventType) {
                case 'charge.success':
                    $reference = (string) ($data['reference'] ?? '');
                    if (!empty($reference)) {
                        $fulfillmentService->fulfillOrder($reference, $data);
                    }
                    $webhook->update([
                        'processing_status' => WebhookStatus::PROCESSED,
                        'processed_at' => Carbon::now(),
                    ]);
                    break;

                default:
                    Log::info("Paystack webhook event '{$eventType}' ignored.");
                    $webhook->update([
                        'processing_status' => WebhookStatus::IGNORED,
                        'processed_at' => Carbon::now(),
                    ]);
                    break;
            }
        } catch (Exception $e) {
            $webhook->update([
                'processing_status' => WebhookStatus::FAILED,
                'last_error_code' => substr($e->getMessage(), 0, 100),
            ]);

            Log::error("Paystack webhook processing failed for event {$webhook->event_identifier}: {$e->getMessage()}");
            throw $e;
        }
    }
}

