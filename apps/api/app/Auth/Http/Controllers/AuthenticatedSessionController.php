<?php

namespace App\Auth\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController
{
    public function store(LoginRequest $request): JsonResponse
    {
        $authenticated = Auth::attempt(
            $request->only('email', 'password'),
            $request->boolean('remember'),
        );

        if (! $authenticated) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
                'errors' => ['email' => ['The provided credentials are incorrect.']],
            ], 422);
        }

        $request->session()->regenerate();

        return response()->json(null, 204);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Password reset is not configured in P0 - accept the request gracefully
        return response()->json(null, 204);
    }
}
