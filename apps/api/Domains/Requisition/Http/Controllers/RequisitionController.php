<?php

namespace Domains\Requisition\Http\Controllers;

use App\Http\Requests\Requisition\CreateRequisitionRequest;
use App\Http\Requests\Requisition\UpdateRequisitionRequest;
use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Requisition\Actions\CreateRequisitionDraft;
use Domains\Requisition\Actions\SubmitRequisition;
use Domains\Requisition\Actions\UpdateRequisitionDraft;
use Domains\Requisition\Http\Resources\RequisitionResource;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequisitionController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant)
    {
        $this->authorize('viewAny', Requisition::class);

        $tenant = $currentTenant->get();
        $user = $request->user();
        $role = $currentTenant->roleFor($user);

        $query = Requisition::query()
            ->with(['requester', 'lineItems'])
            ->where('tenant_id', $tenant->id)
            ->latest('updated_at');

        if ($role === 'buyer' || $role === 'approver') {
            $query->where('status', RequisitionStatus::Submitted);
        } elseif ($role !== 'admin') {
            $query->where('requester_id', $user->id);
        }

        $query->when($request->query('search'), function ($query, string $search): void {
            $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%");
            });
        });

        $query->when($request->query('status'), fn ($query, string $status) => $query->where('status', $status));
        $query->when($request->query('owner'), fn ($query, string $owner) => $query->where('requester_id', $owner));
        $query->when($request->query('neededByFrom'), fn ($query, string $date) => $query->whereDate('needed_by_date', '>=', $date));
        $query->when($request->query('neededByTo'), fn ($query, string $date) => $query->whereDate('needed_by_date', '<=', $date));

        $paginator = $query->paginate((int) $request->integer('perPage', 15));

        return response()->json([
            'data' => RequisitionResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(
        CreateRequisitionRequest $request,
        CurrentTenant $currentTenant,
        CreateRequisitionDraft $createRequisitionDraft,
    ): JsonResponse {
        $requisition = $createRequisitionDraft->handle(
            $currentTenant->get(),
            $request->user(),
            $request->validated(),
        );

        return (new RequisitionResource($requisition))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, CurrentTenant $currentTenant, int $requisition): RequisitionResource
    {
        $requisition = $this->findTenantRequisition($currentTenant, $requisition);

        $this->authorize('view', $requisition);

        return new RequisitionResource($requisition->load(['requester', 'lineItems']));
    }

    public function update(
        UpdateRequisitionRequest $request,
        CurrentTenant $currentTenant,
        UpdateRequisitionDraft $updateRequisitionDraft,
        int $requisition,
    ): RequisitionResource {
        $requisition = $this->findTenantRequisition($currentTenant, $requisition);

        $this->authorize('update', $requisition);

        $requisition = $updateRequisitionDraft->handle(
            $currentTenant->get(),
            $request->user(),
            $requisition,
            $request->validated(),
        );

        return new RequisitionResource($requisition);
    }

    public function submit(
        Request $request,
        CurrentTenant $currentTenant,
        SubmitRequisition $submitRequisition,
        int $requisition,
    ): RequisitionResource {
        $requisition = $this->findTenantRequisition($currentTenant, $requisition);

        $this->authorize('submit', $requisition);

        $requisition = $submitRequisition->handle(
            $currentTenant->get(),
            $request->user(),
            $requisition,
        );

        return new RequisitionResource($requisition);
    }

    private function findTenantRequisition(CurrentTenant $currentTenant, int $id): Requisition
    {
        return Requisition::query()
            ->where('tenant_id', $currentTenant->get()->id)
            ->findOrFail($id);
    }
}
