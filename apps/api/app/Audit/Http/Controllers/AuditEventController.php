<?php

namespace App\Audit\Http\Controllers;

use App\Audit\AuditEvent;
use App\Audit\AuditEventResource;
use App\Audit\AuditSubject;
use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AuditEventController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant)
    {
        $this->authorize('viewAny', AuditEvent::class);

        $validated = $request->validate([
            'action' => ['sometimes', 'string', 'max:120'],
            'actorId' => ['sometimes', 'integer'],
            'subjectType' => ['sometimes', 'string', Rule::in(['requisition'])],
            'subjectId' => ['sometimes', 'integer'],
            'occurredFrom' => ['sometimes', 'date'],
            'occurredTo' => ['sometimes', 'date', 'after_or_equal:occurredFrom'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AuditEvent::query()
            ->with('actor')
            ->where('tenant_id', $currentTenant->get()->id)
            ->latest('occurred_at')
            ->latest('id');

        $query->when($validated['action'] ?? null, fn ($query, string $action) => $query->where('action', $action));
        $query->when($validated['actorId'] ?? null, fn ($query, int $actorId) => $query->where('actor_id', $actorId));
        $query->when($validated['subjectType'] ?? null, fn ($query, string $subjectType) => $query->where('subject_type', AuditSubject::classFor($subjectType)));
        $query->when($validated['subjectId'] ?? null, fn ($query, int $subjectId) => $query->where('subject_id', $subjectId));
        $query->when($validated['occurredFrom'] ?? null, fn ($query, string $date) => $query->where('occurred_at', '>=', Carbon::parse($date)->startOfDay()));
        $query->when($validated['occurredTo'] ?? null, fn ($query, string $date) => $query->where('occurred_at', '<=', Carbon::parse($date)->endOfDay()));

        $perPage = (int) ($validated['perPage'] ?? 25);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => AuditEventResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }
}
