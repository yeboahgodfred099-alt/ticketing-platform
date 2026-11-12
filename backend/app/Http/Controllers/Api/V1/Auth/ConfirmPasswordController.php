<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmPasswordRequest;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class ConfirmPasswordController extends Controller
{
    public function __invoke(ConfirmPasswordRequest $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        if (! Hash::check((string) $request->input('password'), $admin->password)) {
            return response()->json([
                'message' => 'The provided password does not match our records.',
                'code' => 'PASSWORD_MISMATCH',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return response()->json([
            'message' => 'Password confirmed.',
        ], Response::HTTP_OK);
    }
}

