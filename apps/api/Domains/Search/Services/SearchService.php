<?php

namespace Domains\Search\Services;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Search\Contracts\SearchProvider;
use Domains\Search\Data\SearchResultData;
use Domains\Search\Providers\AwardSearchProvider;
use Domains\Search\Providers\PurchaseOrderRequestHandoffSearchProvider;
use Domains\Search\Providers\ProcurementProjectSearchProvider;
use Domains\Search\Providers\QuotationSearchProvider;
use Domains\Search\Providers\RfqSearchProvider;
use Domains\Search\Providers\RequisitionSearchProvider;
use Domains\Search\Providers\VendorSearchProvider;
use Illuminate\Support\Collection;

class SearchService
{
    /**
     * @param array<int, string> $types
     * @return Collection<int, SearchResultData>
     */
    public function search(Tenant $tenant, User $user, string $query, array $types, int $limit): Collection
    {
        $normalizedTypes = array_values(array_unique($types));
        $providers = array_values(array_filter(
            $this->providers(),
            fn (SearchProvider $provider): bool => in_array($provider->type(), $normalizedTypes, true),
        ));

        return collect($providers)
            ->flatMap(fn (SearchProvider $provider): Collection => $provider->search($tenant, $user, $query, $limit))
            ->take($limit)
            ->values();
    }

    /**
     * @return array<int, SearchProvider>
     */
    private function providers(): array
    {
        return [
            app(RequisitionSearchProvider::class),
            app(VendorSearchProvider::class),
            app(ProcurementProjectSearchProvider::class),
            app(RfqSearchProvider::class),
            app(QuotationSearchProvider::class),
            app(AwardSearchProvider::class),
            app(PurchaseOrderRequestHandoffSearchProvider::class),
        ];
    }
}
