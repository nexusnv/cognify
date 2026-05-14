<?php

namespace App\Auth\Http\Controllers;

use App\Auth\Http\Resources\CurrentUserResource;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Notifications\NotificationPreferenceDefaults;
use Illuminate\Http\JsonResponse;

class UserProfileController
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $preferences = $request->has('notificationPreferences')
            ? NotificationPreferenceDefaults::merge(array_merge(
                $user->notification_preferences ?? [],
                $request->input('notificationPreferences'),
            ))
            : NotificationPreferenceDefaults::merge($user->notification_preferences);

        $user->update([
            'name' => $request->input('name'),
            'avatar_url' => $request->input('avatarUrl'),
            'timezone' => $request->input('timezone'),
            'locale' => $request->input('locale'),
            'theme' => $request->input('theme'),
            'notification_preferences' => $preferences,
        ]);

        return response()->json([
            'data' => new CurrentUserResource($user->fresh()),
        ]);
    }
}
