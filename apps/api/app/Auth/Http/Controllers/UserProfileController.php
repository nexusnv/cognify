<?php

namespace App\Auth\Http\Controllers;

use App\Auth\Http\Resources\CurrentUserResource;
use App\Http\Requests\Auth\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;

class UserProfileController
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'name' => $request->input('name'),
            'avatar_url' => $request->input('avatarUrl'),
            'timezone' => $request->input('timezone'),
            'locale' => $request->input('locale'),
            'theme' => $request->input('theme'),
        ]);

        return response()->json([
            'data' => new CurrentUserResource($user->fresh()),
        ]);
    }
}
