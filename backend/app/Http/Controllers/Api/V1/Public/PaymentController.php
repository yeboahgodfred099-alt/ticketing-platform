<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Exceptions\InvalidCheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\InitiatePaymentRequest;
use App\Http\Requests\Public\SubmitOtpRequest;
use App\Services\Payment\PaymentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Initiate Mobile Money payment for an order.
     */
    public function initiate(InitiatePaymentRequest $request): JsonResponse
    {
        try {
            $order = $request->getOrder();
            $phone = (string) $request->input('phone');
            $provider = (string) $request->input('provider');

            $result = $this->paymentService->initiatePayment($order, $phone, $provider);

            return response()->json([
                'data' => $result,
            ], Response::HTTP_OK);
        } catch (InvalidCheckoutException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Payment initiation failed. Please try again.',
            ], Response::HTTP_BAD_GATEWAY);
        }
    }

    /**
     * Submit OTP continuation if requested by network provider.
     */
    public function submitOtp(SubmitOtpRequest $request): JsonResponse
    {
        try {
            $reference = (string) $request->input('reference');
            $otp = (string) $request->input('otp');

            $result = $this->paymentService->submitOtp($reference, $otp);

            return response()->json([
                'data' => $result,
            ], Response::HTTP_OK);
        } catch (InvalidCheckoutException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'OTP submission failed.',
            ], Response::HTTP_BAD_GATEWAY);
        }
    }

    /**
     * Polling endpoint to check payment status by reference.
     */
    public function status(Request $request, string $reference): JsonResponse
    {
        $status = $this->paymentService->getPaymentStatus($reference);

        if (!$status['found']) {
            return response()->json([
                'message' => $status['message'],
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => $status,
        ], Response::HTTP_OK);
    }
}
