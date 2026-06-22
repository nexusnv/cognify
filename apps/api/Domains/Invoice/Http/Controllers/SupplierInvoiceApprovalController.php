<?php

namespace Domains\Invoice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Invoice\Actions\SubmitSupplierInvoiceForApproval;
use Domains\Invoice\Http\Resources\SupplierInvoiceResource;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SupplierInvoiceApprovalController extends Controller
{
    public function __construct(
        private readonly SubmitSupplierInvoiceForApproval $submitAction,
        private readonly CurrentTenant $currentTenant,
    ) {}

    public function submit(Request $request, SupplierInvoice $supplierInvoice): JsonResponse
    {
        $tenant = $this->currentTenant->get();
        $user = $request->user();
        if ($user === null) {
            return response()->json([
                'error' => ['code' => 'unauthenticated', 'message' => 'Unauthenticated.'],
            ], 401);
        }

        Gate::forUser($user)->authorize('submitForApproval', $supplierInvoice);

        $validated = $request->validate([
            'lockVersion' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $invoice = $this->submitAction->handle(
                $supplierInvoice,
                $tenant,
                $user,
                (int) $validated['lockVersion'],
            );

            return response()->json([
                'data' => new SupplierInvoiceResource($invoice),
            ]);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'error' => ['message' => $e->getMessage()],
            ], 409);
        }
    }
}
