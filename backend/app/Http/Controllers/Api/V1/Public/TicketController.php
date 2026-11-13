<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TicketController extends Controller
{
    /**
     * Retrieve and view customer tickets securely using the high-entropy access token.
     */
    public function show(Request $request, string $accessToken): JsonResponse
    {
        $tokenHash = hash('sha256', $accessToken);

        // 1. Find order by access_token_hash
        /** @var Order|null $order */
        $order = Order::where('access_token_hash', $tokenHash)
            ->with(['event', 'items', 'tickets.orderItem'])
            ->first();

        // 2. Fallback check: if accessToken is an order number with X-Order-Token header
        if (!$order) {
            $headerToken = $request->header('X-Order-Token') ?? $request->query('token');
            if (is_string($headerToken) && !empty($headerToken)) {
                $candidate = Order::where('order_number', $accessToken)
                    ->with(['event', 'items', 'tickets.orderItem'])
                    ->first();

                if ($candidate && $candidate->access_token_hash && hash_equals($candidate->access_token_hash, hash('sha256', $headerToken))) {
                    $order = $candidate;
                }
            }
        }

        if (!$order) {
            return response()->json([
                'message' => 'Invalid or unauthorized ticket access link.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($order->status !== OrderStatus::PAID) {
            return response()->json([
                'message' => "Tickets are not yet available. Order status is '{$order->status->value}'.",
                'order_status' => $order->status->value,
            ], Response::HTTP_FORBIDDEN);
        }

        $event = $order->event;

        return response()->json([
            'data' => [
                'order' => [
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'customer_email' => $order->customer_email,
                    'total_amount_minor' => $order->total_amount_minor,
                    'currency' => $order->currency,
                    'paid_at' => $order->paid_at?->toIso8601String(),
                ],
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'banner_url' => $event->banner_url,
                    'venue_name' => $event->venue_name,
                    'venue_address' => $event->venue_address,
                    'starts_at' => $event->starts_at->toIso8601String(),
                    'ends_at' => $event->ends_at->toIso8601String(),
                    'timezone' => $event->timezone,
                ],
                'tickets' => TicketResource::collection($order->tickets),
            ],
        ], Response::HTTP_OK);
    }
}
