<?php

namespace App\Auth\Http\Controllers;

use App\Auth\Http\Resources\CurrentUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->json([
            'data' => new CurrentUserResource($user),
        ]);
    }
}
