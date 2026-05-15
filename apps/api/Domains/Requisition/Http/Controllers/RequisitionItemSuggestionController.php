<?php

namespace Domains\Requisition\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Requisition\Http\Resources\RequisitionItemSuggestionResource;
use Domains\Requisition\Models\RequisitionItemSuggestion;
use Illuminate\Http\Request;

class RequisitionItemSuggestionController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant)
    {
        $tenant = $currentTenant->get();
        $search = mb_strtolower(trim((string) $request->query('search', '')));

        $suggestions = RequisitionItemSuggestion::query()
            ->where('active', true)
            ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
            ->when($request->query('category'), fn ($query, string $category) => $query->where('category', $category))
            ->when($request->query('currency'), fn ($query, string $currency) => $query->where('currency', strtoupper($currency)))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(category) LIKE ?', ["%{$search}%"]);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(10)
            ->get();

        return RequisitionItemSuggestionResource::collection($suggestions);
    }
}
