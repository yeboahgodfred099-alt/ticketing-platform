<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use App\Services\Admin\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $throttleKey = 'login:' . strtolower((string) $request->input('email')) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Too many login attempts. Please try again in {$seconds} seconds.",
                'code' => 'RATE_LIMITED',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $admin = Admin::where('email', $request->input('email'))->first();

        if (! $admin || ! Hash::check($request->input('password'), $admin->password)) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'message' => 'Invalid credentials.',
                'code' => 'INVALID_CREDENTIALS',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $admin->is_active) {
            return response()->json([
                'message' => 'Account is inactive. Contact a Super Administrator.',
                'code' => 'ACCOUNT_INACTIVE',
            ], Response::HTTP_FORBIDDEN);
        }

        RateLimiter::clear($throttleKey);

        // Perform login
        Auth::guard('web')->login($admin);
        $request->session()->regenerate();

        $admin->update(['last_login_at' => now()]);

        $this->auditService->log(
            action: 'admin.login',
            target: $admin,
            metadata: ['status' => 'success'],
            actor: $admin,
            request: $request
        );

        $isSuperAdmin = $admin->hasRole('Super Admin');
        $hasMfa = $admin->hasMfaEnabled();

        // If MFA enabled, require MFA verification step
        if ($hasMfa) {
            $request->session()->put('auth.mfa_verified', false);

            return response()->json([
                'requires_mfa' => true,
                'mfa_setup_required' => false,
                'message' => 'Two-factor authentication code required.',
            ], Response::HTTP_OK);
        }

        // If Super Admin has no MFA, require setup
        if ($isSuperAdmin) {
            $request->session()->put('auth.mfa_verified', false);

            return response()->json([
                'requires_mfa' => true,
                'mfa_setup_required' => true,
                'message' => 'Super Administrator must configure Multi-Factor Authentication.',
            ], Response::HTTP_OK);
        }

        $request->session()->put('auth.mfa_verified', true);
        $request->session()->put('auth.password_confirmed_at', time());

        return response()->json([
            'requires_mfa' => false,
            'admin' => new AdminResource($admin->load('roles.permissions')),
        ], Response::HTTP_OK);
    }
}
