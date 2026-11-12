<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MfaVerifyRequest;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use App\Services\Admin\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response;

class MfaController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Generate new MFA secret and setup QR code payload.
     */
    public function setup(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey(32);

        // Store temporarily in session until confirmed
        $request->session()->put('mfa.pending_secret', $secret);

        $qrUrl = $google2fa->getQRCodeUrl(
            config('app.name', 'ClevaWave Ticketing'),
            $admin->email,
            $secret
        );

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrUrl,
            'message' => 'Scan QR code with your authenticator app (Google Authenticator, Authy, etc.) and submit the verification code.',
        ]);
    }

    /**
     * Confirm MFA setup with first valid code.
     */
    public function confirmSetup(MfaVerifyRequest $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $secret = $request->session()->get('mfa.pending_secret');

        if (! $secret) {
            return response()->json([
                'message' => 'No pending MFA setup found. Please initiate setup again.',
                'code' => 'MFA_NO_PENDING_SETUP',
            ], Response::HTTP_BAD_REQUEST);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($secret, (string) $request->input('code'));

        if (! $valid) {
            return response()->json([
                'message' => 'Invalid authenticator code. Please check your authenticator clock and try again.',
                'code' => 'MFA_INVALID_CODE',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Generate 8 random recovery codes
        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = strtoupper(Str::random(4) . '-' . Str::random(4));
        }

        $admin->update([
            'mfa_secret' => Crypt::encryptString($secret),
            'mfa_enabled_at' => now(),
            'mfa_recovery_codes' => $recoveryCodes,
        ]);

        $request->session()->forget('mfa.pending_secret');
        $request->session()->put('auth.mfa_verified', true);
        $request->session()->put('auth.password_confirmed_at', time());

        $this->auditService->log(
            action: 'admin.mfa_enabled',
            target: $admin,
            metadata: ['status' => 'enabled'],
            actor: $admin,
            request: $request
        );

        return response()->json([
            'message' => 'Multi-Factor Authentication enabled successfully. Store your recovery codes safely.',
            'recovery_codes' => $recoveryCodes,
            'admin' => new AdminResource($admin->fresh()->load('roles.permissions')),
        ]);
    }

    /**
     * Verify MFA code during login.
     */
    public function verify(MfaVerifyRequest $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $throttleKey = 'mfa:' . $admin->id . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Too many verification attempts. Please try again in {$seconds} seconds.",
                'code' => 'RATE_LIMITED',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $code = (string) $request->input('code');

        if (! $admin->hasMfaEnabled()) {
            return response()->json([
                'message' => 'MFA is not enabled for this account.',
                'code' => 'MFA_NOT_CONFIGURED',
            ], Response::HTTP_BAD_REQUEST);
        }

        $secret = Crypt::decryptString($admin->mfa_secret);
        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($secret, $code);

        // Check recovery codes if TOTP code fails
        if (! $valid && is_array($admin->mfa_recovery_codes)) {
            $recoveryCodes = $admin->mfa_recovery_codes;
            $codeIndex = array_search(strtoupper($code), array_map('strtoupper', $recoveryCodes), true);

            if ($codeIndex !== false) {
                // Consume recovery code
                unset($recoveryCodes[$codeIndex]);
                $admin->update(['mfa_recovery_codes' => array_values($recoveryCodes)]);
                $valid = true;

                $this->auditService->log(
                    action: 'admin.mfa_recovery_code_used',
                    target: $admin,
                    metadata: ['remaining_codes_count' => count($recoveryCodes)],
                    actor: $admin,
                    request: $request
                );
            }
        }

        if (! $valid) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'message' => 'Invalid authentication code or recovery code.',
                'code' => 'INVALID_MFA_CODE',
            ], Response::HTTP_UNAUTHORIZED);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->put('auth.mfa_verified', true);
        $request->session()->put('auth.password_confirmed_at', time());

        $this->auditService->log(
            action: 'admin.mfa_verified',
            target: $admin,
            metadata: ['status' => 'verified'],
            actor: $admin,
            request: $request
        );

        return response()->json([
            'message' => 'Two-factor authentication verified.',
            'admin' => new AdminResource($admin->load('roles.permissions')),
        ]);
    }
}
