<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Services\Support\PhoneNormalizer;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackAdapter
{
    protected string $baseUrl;
    protected string $secretKey;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = (string) config('paystack.base_url', 'https://api.paystack.co');
        $this->secretKey = (string) config('paystack.secret_key');
        $this->timeout = (int) config('paystack.timeout', 15);
    }

    /**
     * Initiate a Ghana Mobile Money charge via Paystack Charge API.
     *
     * @param string $reference Unique reference generated for this transaction
     * @param int $amountMinor Amount in minor currency units (pesewas)
     * @param string $email Customer email address
     * @param string $phone Customer phone number
     * @param string $provider Mobile Money provider ('mtn', 'vod', 'atl')
     * @param array<string, mixed> $metadata Arbitrary metadata (order_number, event_id, etc.)
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function chargeMobileMoney(
        string $reference,
        int $amountMinor,
        string $email,
        string $phone,
        string $provider,
        array $metadata = []
    ): array {
        // Paystack Ghana Mobile Money expects 10-digit national format (e.g. 024XXXXXXX)
        $nationalPhone = PhoneNormalizer::toNational($phone);

        $payload = [
            'reference' => $reference,
            'amount' => $amountMinor,
            'email' => $email,
            'currency' => 'GHS',
            'mobile_money' => [
                'phone' => $nationalPhone,
                'provider' => strtolower($provider),
            ],
            'metadata' => $metadata,
        ];

        $response = $this->client()->post("{$this->baseUrl}/charge", $payload);

        return $this->handleResponse($response, "chargeMobileMoney ({$reference})");
    }

    /**
     * Submit OTP continuation for transactions requiring OTP.
     *
     * @param string $otp One-time password entered by customer
     * @param string $reference Transaction reference
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function submitOtp(string $otp, string $reference): array
    {
        $payload = [
            'otp' => trim($otp),
            'reference' => $reference,
        ];

        // Never log the payload containing OTP
        $response = $this->client()->post("{$this->baseUrl}/charge/submit_otp", $payload);

        return $this->handleResponse($response, "submitOtp ({$reference})");
    }

    /**
     * Verify a transaction with Paystack by reference.
     *
     * @param string $reference
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function verifyTransaction(string $reference): array
    {
        $response = $this->client()->get("{$this->baseUrl}/transaction/verify/" . rawurlencode($reference));

        return $this->handleResponse($response, "verifyTransaction ({$reference})");
    }

    /**
     * Create pre-configured HTTP client with Authorization Bearer header.
     */
    protected function client()
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->secretKey}",
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout($this->timeout);
    }

    /**
     * Process Paystack HTTP response.
     *
     * @param Response $response
     * @param string $actionContext
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    protected function handleResponse(Response $response, string $actionContext): array
    {
        $statusCode = $response->status();
        $body = $response->json() ?? [];

        if (!$response->successful()) {
            $errorMessage = $body['message'] ?? "Paystack request failed with status {$statusCode}";
            Log::warning("Paystack API call failed [{$actionContext}]: {$errorMessage}", [
                'status' => $statusCode,
            ]);

            throw new Exception("Payment gateway error: {$errorMessage}", $statusCode);
        }

        if (!isset($body['status']) || $body['status'] !== true) {
            $message = $body['message'] ?? 'Payment provider rejected the transaction.';
            Log::warning("Paystack returned unsuccessful status [{$actionContext}]: {$message}");

            return [
                'status' => false,
                'message' => $message,
                'data' => $body['data'] ?? [],
            ];
        }

        return $body;
    }
}
