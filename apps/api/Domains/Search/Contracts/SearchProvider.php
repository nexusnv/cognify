<?php

namespace Domains\Search\Contracts;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Search\Data\SearchResultData;
use Illuminate\Support\Collection;

interface SearchProvider
{
    public function type(): string;

    /**
     * @return Collection<int, SearchResultData>
     */
    public function search(Tenant $tenant, User $user, string $query, int $limit): Collection;
}
