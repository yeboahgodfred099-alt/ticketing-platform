<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Exceptions\InsufficientInventoryException;
use App\Exceptions\InvalidCheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService
    ) {}

    /**
     * Anonymous guest checkout: reserves inventory and creates order.
     */
    public function checkout(CheckoutRequest $request): JsonResponse
    {
        try {
            $result = $this->checkoutService->reserveOrder($request->validated());

            return (new OrderResource($result['order']))
                ->withAccessToken($result['raw_access_token'])
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (InsufficientInventoryException|InvalidCheckoutException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'INVENTORY_UNAVAILABLE',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Retrieve order details securely using order number and high-entropy access token.
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)
            ->with(['items', 'event'])
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Validate access token via constant-time comparison
        $token = $request->header('X-Order-Token') ?? $request->query('token');
        if (!is_string($token) || empty($token)) {
            return response()->json([
                'message' => 'Access token required to view order details.',
            ], Response::HTTP_FORBIDDEN);
        }

        $providedHash = hash('sha256', $token);
        if (!$order->access_token_hash || !hash_equals($order->access_token_hash, $providedHash)) {
            return response()->json([
                'message' => 'Invalid or unauthorized order access token.',
            ], Response::HTTP_FORBIDDEN);
        }

        return (new OrderResource($order))->response();
    }
}

