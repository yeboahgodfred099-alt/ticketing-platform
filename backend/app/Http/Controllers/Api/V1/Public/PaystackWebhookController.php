<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\WebhookStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaystackWebhook;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Webhook\WebhookSignatureValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PaystackWebhookController extends Controller
{
    public function __construct(
        protected WebhookSignatureValidator $signatureValidator
    ) {}

    /**
     * Handle incoming Paystack webhook requests.
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Verify HMAC-SHA512 Signature against raw request bytes
        if (!$this->signatureValidator->isValid($request)) {
            Log::warning("Paystack webhook rejected: invalid signature", [
                'ip' => $request->ip(),
                'signature' => $request->header('x-paystack-signature'),
            ]);

            return response()->json([
                'message' => 'Invalid signature.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            return response()->json([
                'message' => 'Invalid JSON payload.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $event = (string) ($payload['event'] ?? 'unknown');
        $data = $payload['data'] ?? [];
        $reference = (string) ($data['reference'] ?? '');

        // Form unique event identifier for idempotency
        $dataId = isset($data['id']) ? (string) $data['id'] : null;
        $eventIdentifier = $dataId
            ? "paystack:{$event}:{$dataId}"
            : "paystack:{$event}:" . ($reference ?: hash('sha256', $rawBody));

        // 2. Idempotency Check: deduplicate identical events
        $existingWebhook = WebhookEvent::where('provider', 'paystack')
            ->where('event_identifier', $eventIdentifier)
            ->first();

        if ($existingWebhook) {
            Log::info("Paystack webhook '{$eventIdentifier}' already received. Skipping duplicate.");
            return response()->json([
                'status' => true,
                'message' => 'Webhook already received.',
            ], Response::HTTP_OK);
        }

        // 3. Resolve matching payment row if reference provided
        $payment = !empty($reference)
            ? Payment::where('reference', $reference)->first()
            : null;

        // 4. Record WebhookEvent safely
        $webhookEvent = WebhookEvent::create([
            'provider' => 'paystack',
            'event_identifier' => $eventIdentifier,
            'event_type' => $event,
            'payment_id' => $payment?->id,
            'payload_hash' => hash('sha256', $rawBody),
            'processing_status' => WebhookStatus::QUEUED,
            'received_at' => Carbon::now(),
        ]);

        // 5. Dispatch async processing job
        ProcessPaystackWebhook::dispatch($webhookEvent->id, $payload);

        // 6. Acknowledge promptly with 200 OK
        return response()->json([
            'status' => true,
            'message' => 'Webhook received and queued for processing.',
        ], Response::HTTP_OK);
    }
}

