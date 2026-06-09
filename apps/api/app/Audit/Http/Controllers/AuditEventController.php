<?php

namespace App\Audit\Http\Controllers;

use App\Audit\AuditEvent;
use App\Audit\AuditEventResource;
use App\Audit\AuditSubject;
use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class AuditEventController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant)
    {
        $this->authorize('viewAny', AuditEvent::class);

        $validated = $request->validate([
            'action' => ['sometimes', 'string', 'max:120'],
            'actorId' => ['sometimes', 'integer'],
            'subjectType' => ['sometimes', 'string', Rule::in(AuditSubject::publicTypes())],
            'subjectId' => ['sometimes', 'string', 'max:255'],
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
        if (($validated['subjectType'] ?? null) !== null) {
            $subjectClass = AuditSubject::classFor($validated['subjectType']);

            if ($subjectClass === null) {
                throw ValidationException::withMessages([
                    'subjectType' => ['The selected subject type is invalid.'],
                ]);
            }

            $query->where('subject_type', $subjectClass);
        }
        if (array_key_exists('subjectId', $validated)) {
            $query->where('subject_id', $validated['subjectId']);
        }
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
