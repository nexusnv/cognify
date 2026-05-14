<?php

namespace Domains\Search\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Search\Http\Requests\SearchRequest;
use Domains\Search\Http\Resources\SearchResultResource;
use Domains\Search\Services\SearchService;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function index(
        SearchRequest $request,
        CurrentTenant $currentTenant,
        SearchService $searchService,
    ): JsonResponse {
        $query = $request->normalizedQuery();
        $limit = $request->resultLimit();
        $results = $searchService->search(
            tenant: $currentTenant->get(),
            user: $request->user(),
            query: $query,
            types: $request->typeFilters(),
            limit: $limit,
        );

        return response()->json([
            'data' => SearchResultResource::collection($results)->resolve(),
            'meta' => [
                'query' => $query,
                'limit' => $limit,
                'returned' => $results->count(),
            ],
        ]);
    }
}
