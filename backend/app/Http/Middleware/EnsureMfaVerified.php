<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaVerified
{
    /**
     * Handle an incoming request.
     * Enforce MFA check for privileged administrator sessions.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        if (! $admin instanceof Admin) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'code' => 'UNAUTHENTICATED',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check if admin is active
        if (! $admin->is_active) {
            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'message' => 'Account is deactivated. Contact a Super Administrator.',
                'code' => 'ACCOUNT_DEACTIVATED',
            ], Response::HTTP_FORBIDDEN);
        }

        // Super Admins must always have MFA configured and verified
        $isSuperAdmin = $admin->hasRole('Super Admin');

        if ($isSuperAdmin && ! $admin->hasMfaEnabled()) {
            return response()->json([
                'message' => 'Multi-Factor Authentication setup is mandatory for Super Administrators.',
                'code' => 'MFA_SETUP_REQUIRED',
            ], Response::HTTP_FORBIDDEN);
        }

        // If admin has MFA enabled, check if current session has verified it
        if ($admin->hasMfaEnabled() && ! $request->session()->get('auth.mfa_verified', false)) {
            return response()->json([
                'message' => 'Two-factor authentication required for this session.',
                'code' => 'MFA_VERIFICATION_REQUIRED',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
