<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireReauthentication
{
    /**
     * Reauthentication window in seconds (15 minutes).
     */
    private const WINDOW = 900;

    public function handle(Request $request, Closure $next): Response
    {
        $lastAuth = $request->session()->get('auth.password_confirmed_at');

        if (! $lastAuth || (time() - $lastAuth) > self::WINDOW) {
            return response()->json([
                'message' => 'Password confirmation required for sensitive operation.',
                'code' => 'PASSWORD_CONFIRMATION_REQUIRED',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
