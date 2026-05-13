# Search And Command Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Cognify's P0 command palette and tenant-scoped global search foundation, starting with keyboard-first navigation, create-requisition command, session recent records, and permission-aware requisition search.

**Architecture:** Keep command behavior in `apps/web` as app-shell workflow code, use `cmdk` plus existing shell route metadata for accessible local commands, and add a narrow `apps/api/Domains/Search` backend that coordinates searchable providers. P0 search is database-backed and requisition-only, with OpenAPI-generated client integration and MSW-backed frontend tests.

**Tech Stack:** Next.js App Router, React 19, TypeScript, cmdk, Radix/Dialog primitives through existing stack, TanStack Query, MSW, Vitest, Testing Library, Laravel 12, Sanctum, Eloquent, OpenAPI JSON, Orval.

---

## Runbook Alignment

Follow `docs/05-runbooks/feature-development.md`:

1. Workflow map: user opens command palette, runs local command, searches tenant-visible requisitions.
2. API contract: generic search result shape with requisition provider in P0.
3. Mocked frontend workflow: command palette and MSW search before backend integration.
4. Backend domain behavior: search request validation, provider, tenant and permission filtering.
5. Real API integration: regenerate `@cognify/api-client`, consume generated endpoint through feature hooks.
6. Hardening: keyboard accessibility, stale query handling, tenant isolation, validation, and narrow checks.

## Source Documents

- Spec: `docs/superpowers/specs/2026-05-14-search-command-foundation-design.md`
- P0 epic list: `docs/02-release-management/2026-05-12-P0-Epics.md`
- Roadmap: `docs/01-product/feature-roadmap.md`
- Feature runbook: `docs/05-runbooks/feature-development.md`
- Architecture baseline: `docs/superpowers/specs/2026-05-09-cognify-greenfield-saas-runbook-design.md`

## File Structure

Create:

- `apps/api/Domains/Search/Contracts/SearchProvider.php`: provider interface for future record types.
- `apps/api/Domains/Search/Data/SearchResultData.php`: typed backend DTO for result shape.
- `apps/api/Domains/Search/Http/Controllers/SearchController.php`: thin API controller for `GET /api/search`.
- `apps/api/Domains/Search/Http/Requests/SearchRequest.php`: query/types/limit validation.
- `apps/api/Domains/Search/Http/Resources/SearchResultResource.php`: stable response shape.
- `apps/api/Domains/Search/Providers/RequisitionSearchProvider.php`: tenant and permission-aware requisition search.
- `apps/api/Domains/Search/Services/SearchService.php`: provider coordination, type filtering, limit enforcement.
- `apps/api/tests/Feature/SearchApiTest.php`: backend search contract, tenant, role, validation tests.
- `apps/web/features/search/api/search-api.ts`: generated-client wrapper and active-tenant header.
- `apps/web/features/search/components/command-palette.tsx`: app command dialog and grouped results.
- `apps/web/features/search/components/command-palette-item.tsx`: item rendering for local commands/search results.
- `apps/web/features/search/hooks/use-global-search.ts`: debounced query hook.
- `apps/web/features/search/hooks/use-recent-records.ts`: session recent-record store hook.
- `apps/web/features/search/mocks/search-fixtures.ts`: OpenAPI-shaped search result fixtures.
- `apps/web/features/search/mocks/search-handlers.ts`: MSW search handler.
- `apps/web/features/search/search-commands.ts`: local command registry.
- `apps/web/features/search/types/search-view-model.ts`: app command and result view types.
- `apps/web/features/search/tests/command-palette.test.tsx`: shell command workflow tests.

Modify:

- `apps/api/routes/api.php`: add `GET /api/search` under protected tenant middleware.
- `apps/api/storage/openapi/openapi.json`: add search schemas and endpoint.
- `packages/api-client/src/generated/*`: regenerate.
- `apps/web/components/shell/command-palette-host.tsx`: replace inert button with real host.
- `apps/web/components/shell/app-shell.test.tsx`: keep shell behavior covered with real host.
- `apps/web/features/requisitions/workflows/requisition-detail-page.tsx`: record opened requisitions in recent-record session store.
- `apps/web/tests/msw/handlers.ts`: register search handler.

Do not modify `packages/ui`. Command palette behavior is Cognify app-shell workflow code.

## Workflow Map

```txt
Open:
  authenticated user clicks shell Search button or presses Cmd/Ctrl+K
  -> command palette opens with focus in search input
  -> local navigation/actions are immediately available

Local command:
  user selects Open requisitions or Create requisition
  -> Next router navigates to the route
  -> palette closes

Remote search:
  user types at least two characters
  -> frontend debounces and calls GET /api/search
  -> backend validates query/types/limit
  -> SearchService invokes RequisitionSearchProvider
  -> provider scopes by current tenant and existing role visibility
  -> frontend renders grouped result rows
  -> user selects result
  -> Next router navigates and palette closes

Recent record:
  user opens a requisition workspace
  -> frontend records id/title/subtitle/href/status in session storage
  -> palette shows recent records before remote query or below commands
```

Failure paths:

- Query shorter than two characters: frontend does not call remote search; backend also returns validation error if called.
- Unsupported type filter: `422 validation_failed`.
- Cross-tenant record: omitted from results.
- Role cannot view draft: omitted from buyer/approver results.
- Search failure: palette shows an error state and clears stale remote results.

## Task 1: Backend Search Regression Tests First

**Files:**

- Create: `apps/api/tests/Feature/SearchApiTest.php`
- Read: `apps/api/Domains/Requisition/Http/Controllers/RequisitionController.php`
- Read: `apps/api/Domains/Requisition/Policies/RequisitionPolicy.php`

- [ ] **Step 1: Confirm baseline**

Run:

```bash
git status --short --branch
```

Expected: current branch only. If unrelated modified files exist, do not edit or revert them.

- [ ] **Step 2: Add failing search API tests**

Create `apps/api/tests/Feature/SearchApiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_search_own_requisitions(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        $match = $this->requisition($tenant, $requester, [
            'number' => 'REQ-2026-0042',
            'title' => 'Office fit-out procurement',
        ]);
        $this->requisition($tenant, $requester, ['title' => 'Warehouse supplies']);

        $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/search?query=office&types=requisition&limit=10')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'requisition')
            ->assertJsonPath('data.0.id', (string) $match->id)
            ->assertJsonPath('data.0.title', 'Office fit-out procurement')
            ->assertJsonPath('data.0.subtitle', 'REQ-2026-0042')
            ->assertJsonPath('data.0.href', "/requisitions/{$match->id}")
            ->assertJsonPath('meta.query', 'office');
    }

    public function test_buyer_search_only_returns_submitted_requisitions(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $this->requisition($tenant, $requester, [
            'title' => 'Laptop draft',
            'status' => RequisitionStatus::Draft,
        ]);
        $submitted = $this->requisition($tenant, $requester, [
            'title' => 'Laptop submitted',
            'status' => RequisitionStatus::Submitted,
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/search?query=laptop')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $submitted->id);
    }

    public function test_search_is_tenant_scoped(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [$otherTenant, $otherUser] = $this->tenantUser('requester');
        $this->requisition($tenant, $requester, ['title' => 'Visible laptop']);
        $this->requisition($otherTenant, $otherUser, ['title' => 'Hidden laptop']);

        $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/search?query=laptop')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Visible laptop');
    }

    public function test_search_validates_query_type_and_limit(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/search?query=a')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');

        $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/search?query=office&types=vendor')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');

        $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/search?query=office&limit=250')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_search_returns_empty_success_response(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/search?query=missing')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.returned', 0);
    }

    private function requisition(Tenant $tenant, User $requester, array $overrides = []): Requisition
    {
        return Requisition::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'number' => 'REQ-2026-000001',
            'title' => 'Default requisition',
            'business_justification' => 'Search test',
            'needed_by_date' => '2026-07-15',
            'currency' => 'MYR',
            'status' => RequisitionStatus::Draft,
        ], $overrides));
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantUser(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => fake()->company()]);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }
}
```

- [ ] **Step 3: Run tests and verify failure**

Run:

```bash
cd apps/api
php artisan test --filter=SearchApiTest
```

Expected: FAIL because `/api/search` does not exist.

- [ ] **Step 4: Commit test scaffold**

```bash
git add apps/api/tests/Feature/SearchApiTest.php
git commit -m "test(api): add global search regressions"
```

## Task 2: Backend Search Domain

**Files:**

- Create all backend search files listed in File Structure.
- Modify `apps/api/routes/api.php`

- [ ] **Step 1: Create DTO and provider contract**

Create `SearchResultData`:

```php
<?php

namespace Domains\Search\Data;

class SearchResultData
{
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly ?string $status,
        public readonly string $href,
        public readonly ?string $updatedAt,
    ) {
    }
}
```

Create `SearchProvider`:

```php
<?php

namespace Domains\Search\Contracts;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Support\Collection;

interface SearchProvider
{
    public function type(): string;

    /**
     * @return Collection<int, \Domains\Search\Data\SearchResultData>
     */
    public function search(Tenant $tenant, User $user, string $query, int $limit): Collection;
}
```

- [ ] **Step 2: Create request validation**

Create `SearchRequest`:

```php
<?php

namespace Domains\Search\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2', 'max:120'],
            'types' => ['sometimes', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
        ];
    }

    public function typeFilters(): array
    {
        $types = $this->query('types');
        if (! is_string($types) || trim($types) === '') {
            return ['requisition'];
        }

        return collect(explode(',', $types))
            ->map(fn (string $type) => trim($type))
            ->filter()
            ->each(fn (string $type) => validator(['type' => $type], ['type' => Rule::in(['requisition'])])->validate())
            ->values()
            ->all();
    }

    public function resultLimit(): int
    {
        return max(1, min($this->integer('limit', 10), 25));
    }
}
```

- [ ] **Step 3: Create requisition provider**

Create `RequisitionSearchProvider` using the same visibility logic as `RequisitionController::index`:

```php
$role = app(CurrentTenant::class)->roleFor($user);
$queryBuilder = Requisition::query()
    ->with('requester')
    ->where('tenant_id', $tenant->id);

if ($role === 'buyer' || $role === 'approver') {
    $queryBuilder->where('status', RequisitionStatus::Submitted);
} elseif ($role !== 'admin') {
    $queryBuilder->where('requester_id', $user->id);
}

$queryBuilder->where(function ($builder) use ($query): void {
    $builder->where('number', 'like', "{$query}%")
        ->orWhere('number', 'like', "%{$query}%")
        ->orWhere('title', 'like', "{$query}%")
        ->orWhere('title', 'like', "%{$query}%")
        ->orWhereHas('requester', fn ($requesterQuery) => $requesterQuery->where('name', 'like', "%{$query}%"));
});
```

Map each result to:

```php
new SearchResultData(
    type: 'requisition',
    id: (string) $requisition->id,
    title: $requisition->title,
    subtitle: $requisition->number,
    status: $requisition->status->value,
    href: "/requisitions/{$requisition->id}",
    updatedAt: $requisition->updated_at?->toJSON(),
)
```

- [ ] **Step 4: Create service, resource, and controller**

`SearchService::search()` filters providers by requested types, calls each provider, merges results, caps the total to `limit`, and returns a collection.

`SearchResultResource` returns:

```php
[
    'type' => $result->type,
    'id' => $result->id,
    'title' => $result->title,
    'subtitle' => $result->subtitle,
    'status' => $result->status,
    'href' => $result->href,
    'updatedAt' => $result->updatedAt,
]
```

`SearchController::index()`:

```php
public function index(SearchRequest $request, CurrentTenant $currentTenant, SearchService $search): JsonResponse
{
    $results = $search->search(
        tenant: $currentTenant->get(),
        user: $request->user(),
        query: trim((string) $request->query('query')),
        types: $request->typeFilters(),
        limit: $request->resultLimit(),
    );

    return response()->json([
        'data' => SearchResultResource::collection($results)->resolve(),
        'meta' => [
            'query' => trim((string) $request->query('query')),
            'limit' => $request->resultLimit(),
            'returned' => $results->count(),
        ],
    ]);
}
```

- [ ] **Step 5: Register route**

In `apps/api/routes/api.php` inside `ResolveCurrentTenant`:

```php
Route::get('/search', [SearchController::class, 'index'])->middleware('throttle:60,1');
```

- [ ] **Step 6: Run backend checks**

```bash
cd apps/api
php artisan test --filter=SearchApiTest
php artisan test --filter=RequisitionApiTest
php artisan route:list --path=api
```

Expected: PASS and `/api/search` appears.

- [ ] **Step 7: Commit backend search**

```bash
git add apps/api/Domains/Search apps/api/routes/api.php apps/api/tests/Feature/SearchApiTest.php
git commit -m "feat(api): add tenant scoped global search"
```

## Task 3: OpenAPI Contract And Generated Client

**Files:**

- Modify: `apps/api/storage/openapi/openapi.json`
- Modify: `packages/api-client/src/generated/*`

- [ ] **Step 1: Add search contract**

Add schemas:

- `SearchResult`
- `SearchResponse`
- `SearchMeta`
- `ListGlobalSearchParams`

Add endpoint:

```txt
GET /api/search
operationId: listGlobalSearch
parameters: query, types, limit
responses: 200, 400, 401, 403, 422, 429
```

- [ ] **Step 2: Regenerate and verify**

```bash
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/api-client typecheck
```

Expected: generated endpoint `listGlobalSearch` and search schemas exist.

- [ ] **Step 3: Commit contract**

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src/generated
git commit -m "feat(api-client): add global search contract"
```

## Task 4: MSW-Backed Search Hooks And Command Palette Tests

**Files:**

- Create all `apps/web/features/search/*` files listed in File Structure.
- Modify `apps/web/tests/msw/handlers.ts`

- [ ] **Step 1: Add generated-client wrapper**

Create `apps/web/features/search/api/search-api.ts`:

```ts
import { listGlobalSearch } from "@cognify/api-client/endpoints";
import type { SearchResult } from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "../../identity/api/identity-api";

export async function searchRecords(query: string, types?: string[], limit = 10) {
  const response = await listGlobalSearch(
    { query, types: types?.join(","), limit },
    withActiveTenantHeader(),
  );
  if (response.status !== 200) throw response.data;
  return response.data.data as SearchResult[];
}

function withActiveTenantHeader(): RequestInit | undefined {
  const tenantId = getStoredActiveTenantId();
  return tenantId ? { headers: { "X-Tenant-Id": tenantId } } : undefined;
}
```

- [ ] **Step 2: Add search hook**

Create `use-global-search.ts`:

```ts
import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { searchRecords } from "../api/search-api";

export function useGlobalSearch(query: string) {
  const debouncedQuery = useDebouncedValue(query.trim(), 250);

  return useQuery({
    queryKey: ["global-search", debouncedQuery],
    queryFn: () => searchRecords(debouncedQuery, ["requisition"], 10),
    enabled: debouncedQuery.length >= 2,
    placeholderData: (previous) => previous,
  });
}

function useDebouncedValue(value: string, delay: number) {
  const [debouncedValue, setDebouncedValue] = useState(value);

  useEffect(() => {
    const timeout = window.setTimeout(() => setDebouncedValue(value), delay);
    return () => window.clearTimeout(timeout);
  }, [delay, value]);

  return debouncedValue;
}
```

- [ ] **Step 3: Add local command registry**

Create `search-commands.ts`:

```ts
import type { SearchCommand } from "./types/search-view-model";

export const searchCommands: SearchCommand[] = [
  {
    id: "open-dashboard",
    group: "Navigation",
    label: "Open dashboard",
    description: "Go to the main dashboard.",
    href: "/dashboard",
    keywords: ["home", "overview"],
    enabled: true,
  },
  {
    id: "open-requisitions",
    group: "Navigation",
    label: "Open requisitions",
    description: "Review requisition drafts and submitted requests.",
    href: "/requisitions",
    keywords: ["requests", "purchase"],
    enabled: true,
  },
  {
    id: "create-requisition",
    group: "Actions",
    label: "Create requisition",
    description: "Start a new purchase request.",
    href: "/requisitions/new",
    keywords: ["new", "request", "purchase"],
    enabled: true,
  },
  {
    id: "open-settings",
    group: "Navigation",
    label: "Open account settings",
    description: "Manage profile and preferences.",
    href: "/settings/account",
    keywords: ["profile", "preferences"],
    enabled: true,
  },
];
```

- [ ] **Step 4: Add MSW handler**

Create `search-handlers.ts` with `GET /api/search`. It should filter `searchFixtures` by query and reject `types=vendor` with a `422` error envelope. Register it in `apps/web/tests/msw/handlers.ts`.

- [ ] **Step 5: Add failing command palette tests**

Create `apps/web/features/search/tests/command-palette.test.tsx` with tests:

- Button opens dialog.
- `Control+K` opens dialog.
- Escape closes dialog.
- Create requisition command links to `/requisitions/new`.
- Typing `office` renders requisition search result.
- Unsupported/error response shows an error and does not keep stale results.

Use `QueryClientProvider` and mock `next/navigation` router push.

- [ ] **Step 6: Commit tests and scaffolding**

```bash
git add apps/web/features/search apps/web/tests/msw/handlers.ts
git commit -m "test(web): add command palette search workflow"
```

## Task 5: Command Palette Implementation And Shell Integration

**Files:**

- Modify: `apps/web/components/shell/command-palette-host.tsx`
- Modify: `apps/web/components/shell/app-shell.test.tsx`
- Create/update: `apps/web/features/search/components/command-palette.tsx`
- Create/update: `apps/web/features/search/components/command-palette-item.tsx`
- Create/update: `apps/web/features/search/hooks/use-recent-records.ts`
- Modify: `apps/web/features/requisitions/workflows/requisition-detail-page.tsx`

- [ ] **Step 1: Implement recent records hook**

Use session storage key `cognify.recentRecords.v1`. Keep only 8 records.

```ts
export function rememberRecentRecord(record: RecentRecord) {
  const next = [record, ...readRecentRecords().filter((item) => item.href !== record.href)].slice(0, 8);
  window.sessionStorage.setItem(recentRecordsKey, JSON.stringify(next));
}
```

Guard all storage access with `typeof window === "undefined"`.

- [ ] **Step 2: Record opened requisitions**

In `RequisitionDetailPage`, after requisition data loads, call `rememberRecentRecord` with:

```ts
{
  type: "requisition",
  id: requisition.id,
  title: requisition.title,
  subtitle: requisition.number,
  status: requisition.status,
  href: `/requisitions/${requisition.id}`,
}
```

- [ ] **Step 3: Implement CommandPalette**

Use `cmdk` and a dialog surface. Required behavior:

- Dialog has accessible title `Command menu`.
- Input placeholder `Search or jump to...`.
- Local commands render immediately.
- Recent records render when present.
- Remote search results render when query length is at least 2.
- Loading, empty, and error states are explicit.
- Selecting item with `href` calls `router.push(href)` and closes.

- [ ] **Step 4: Replace shell host**

Modify `CommandPaletteHost`:

```tsx
"use client";

import { Search } from "lucide-react";
import { useEffect, useState } from "react";
import { CommandPalette } from "@/features/search/components/command-palette";

export function CommandPaletteHost() {
  const [open, setOpen] = useState(false);

  useEffect(() => {
    function handleKeyDown(event: KeyboardEvent) {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k") {
        event.preventDefault();
        setOpen((value) => !value);
      }
    }

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, []);

  return (
    <>
      <button
        type="button"
        className="inline-flex min-h-10 items-center gap-2 rounded-md border px-3 text-sm text-muted-foreground hover:text-foreground"
        aria-label="Open command palette"
        onClick={() => setOpen(true)}
      >
        <Search className="h-4 w-4" aria-hidden="true" />
        <span className="hidden sm:inline">Search</span>
      </button>
      <CommandPalette open={open} onOpenChange={setOpen} />
    </>
  );
}
```

- [ ] **Step 5: Run command palette tests**

```bash
pnpm --filter @cognify/web test -- features/search/tests/command-palette.test.tsx components/shell/app-shell.test.tsx
```

Expected: PASS.

- [ ] **Step 6: Commit shell integration**

```bash
git add apps/web/components/shell/command-palette-host.tsx apps/web/components/shell/app-shell.test.tsx apps/web/features/search apps/web/features/requisitions/workflows/requisition-detail-page.tsx
git commit -m "feat(web): add command palette search"
```

## Task 6: Final Integration And Verification

**Files:**

- Review all touched files.
- No new files unless validation exposes a defect.

- [ ] **Step 1: Run backend checks**

```bash
cd apps/api
php artisan test --filter=SearchApiTest
php artisan test --filter=RequisitionApiTest
php artisan route:list --path=api
```

Expected: PASS.

- [ ] **Step 2: Run contract checks**

```bash
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/api-client typecheck
```

Expected: PASS and no unexpected generated diff after the second run.

- [ ] **Step 3: Run web checks**

```bash
pnpm --filter @cognify/web test
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web lint
```

Expected: PASS.

- [ ] **Step 4: Review architecture boundaries**

Confirm:

- No app-specific command behavior in `packages/ui`.
- Search API returns no unauthorized or cross-tenant records.
- The frontend does not issue remote search calls for one-character queries.
- Local commands do not perform server-side mutations.
- Search MSW fixtures are only used in handlers/tests, not production components.

- [ ] **Step 5: Final commit if hardening changed files**

If checks required fixes:

```bash
git add apps/api/Domains/Search apps/api/routes/api.php apps/api/storage/openapi/openapi.json apps/api/tests/Feature/SearchApiTest.php apps/web/components/shell/command-palette-host.tsx apps/web/components/shell/app-shell.test.tsx apps/web/features/requisitions/workflows/requisition-detail-page.tsx apps/web/features/search apps/web/tests/msw/handlers.ts packages/api-client/src/generated
git commit -m "fix: harden command search workflow"
```

If no fixes were needed, do not create an empty commit.

## Plan Self-Review

- Spec coverage: shell button, keyboard shortcut, local commands, recent records, requisition search, tenant scope, role filtering, OpenAPI, generated client, MSW, loading/empty/error states, and keyboard tests are assigned to tasks.
- Scope check: semantic search, saved search, advanced syntax, server-side command execution, analytics, external search infrastructure, and evidence document search are not included.
- Architecture check: backend search coordination lives under `apps/api/Domains/Search`; app command behavior lives under `apps/web/features/search`; `packages/ui` remains untouched.
- Verification check: backend, contract, generated client, web tests, typecheck, lint, and route checks are included.
