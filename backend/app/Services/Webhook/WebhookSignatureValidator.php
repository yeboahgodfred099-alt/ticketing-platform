<?php

declare(strict_types=1);

namespace App\Services\Webhook;

use Illuminate\Http\Request;

class WebhookSignatureValidator
{
    /**
     * Validate the Paystack webhook signature using HMAC-SHA512.
     *
     * @param Request $request
     * @param string|null $secretKeyOverride
     * @return bool
     */
    public function isValid(Request $request, ?string $secretKeyOverride = null): bool
    {
        $signature = $request->header('x-paystack-signature') ?? $request->server('HTTP_X_PAYSTACK_SIGNATURE');

        if (!is_string($signature) || empty($signature)) {
            return false;
        }

        $secret = $secretKeyOverride
            ?: config('paystack.webhook_secret')
            ?: config('paystack.secret_key');

        if (!is_string($secret) || empty($secret)) {
            return false;
        }

        $rawBody = $request->getContent();
        $expectedSignature = hash_hmac('sha512', $rawBody, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
