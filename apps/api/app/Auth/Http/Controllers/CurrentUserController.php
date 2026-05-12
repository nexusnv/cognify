<?php

namespace App\Auth\Http\Controllers;

use App\Auth\Http\Resources\CurrentUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new CurrentUserResource($request->user()),
        ]);
    }
}
