<?php

namespace App\Notifications\Http\Controllers;

use App\Notifications\Http\Resources\NotificationResource;
use App\Notifications\NotificationRecord;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:all,unread,read'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $status = $validated['status'] ?? 'all';
        $limit = (int) ($validated['limit'] ?? 20);
        $tenant = $currentTenant->get();
        $user = $request->user();

        $baseQuery = NotificationRecord::query()
            ->where('tenant_id', $tenant->id)
            ->where('recipient_id', $user->id);

        $unreadCount = (clone $baseQuery)->whereNull('read_at')->count();
        $records = (clone $baseQuery)
            ->when($status === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($status === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->with(['actor'])
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => NotificationResource::collection($records),
            'meta' => [
                'unreadCount' => $unreadCount,
                'returned' => $records->count(),
                'status' => $status,
            ],
        ]);
    }

    public function markRead(Request $request, CurrentTenant $currentTenant, NotificationRecord $notification): JsonResponse
    {
        $tenant = $currentTenant->get();
        $user = $request->user();

        if ($notification->tenant_id !== $tenant->id || $notification->recipient_id !== $user->id) {
            abort(404);
        }

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return response()->json([
            'data' => new NotificationResource($notification->refresh()->load(['actor'])),
        ]);
    }

    public function markAllRead(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $tenant = $currentTenant->get();
        $user = $request->user();

        $query = NotificationRecord::query()
            ->where('tenant_id', $tenant->id)
            ->where('recipient_id', $user->id)
            ->whereNull('read_at');

        $marked = (clone $query)->count();
        $query->update([
            'read_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => ['marked' => $marked],
            'meta' => ['unreadCount' => 0],
        ]);
    }
}
