# Quotation Upload Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement P1-26 so buyers and token-authenticated vendors can upload quotation files against RFQ invitations, creating one durable tenant-scoped quotation record per invitation and attaching uploaded files as quotation evidence.

**Architecture:** Extend the existing Quotation domain and polymorphic Attachment baseline. Vendor uploads resolve access through RFQ invitation portal tokens. Buyer uploads use authenticated tenant middleware. Both upload paths share quotation creation and attachment storage actions so audit, tenant isolation, and attachment metadata remain consistent.

**Tech Stack:** Laravel 12 API, Sanctum tenant middleware, OpenAPI 3.1, Orval generated TypeScript client, Next.js App Router, React Query, MSW, Vitest, shadcn/Radix primitives from `@cognify/ui`.

---

## Source Documents

- `docs/superpowers/specs/2026-05-20-quotation-upload-design.md`
- `docs/superpowers/plans/2026-05-20-vendor-portal-baseline.md`
- `docs/superpowers/plans/2026-05-14-file-attachment-baseline.md`
- `docs/01-product/feature-roadmap.md`

## Scope Boundaries

In scope:

- Vendor upload from the existing vendor RFQ portal using `POST /api/vendor-portal/rfq-invitations/{token}/quotation/attachments`.
- Buyer upload from the sourcing workspace using `POST /api/rfq-invitations/{invitation}/quotation/attachments`.
- Quotation lookup for both surfaces.
- Quotation attachment listing for authenticated buyers.
- One active quotation per RFQ invitation.
- Attachment evidence, quotation metadata, audit events, generated API client usage, MSW-backed UI tests.

Out of scope:

- Manual structured quotation entry, covered by `2026-05-20-quotation-manual-entry-design.md`.
- Quotation versioning, covered by `2026-05-20-quotation-versioning-design.md`.
- OCR, extraction, normalization, comparison, scoring, award recommendation, and vendor account authentication.
- Vendor deletion of submitted files.
- Email intake and standalone quotation workspace.

## File Map

Backend files to add:

- `apps/api/database/migrations/2026_05_20_020000_extend_quotations_for_upload_capture.php`
- `apps/api/Domains/Quotation/States/QuotationStatus.php`
- `apps/api/Domains/Quotation/States/QuotationSubmissionSource.php`
- `apps/api/Domains/Quotation/Actions/CreateOrRevealQuotationForInvitation.php`
- `apps/api/Domains/Quotation/Actions/StoreQuotationAttachment.php`
- `apps/api/Domains/Quotation/Http/Controllers/RfqInvitationQuotationController.php`
- `apps/api/Domains/Quotation/Http/Controllers/VendorPortalQuotationController.php`
- `apps/api/Domains/Quotation/Http/Resources/QuotationResource.php`
- `apps/api/tests/Feature/QuotationUploadApiTest.php`

Backend files to modify:

- `apps/api/Domains/Quotation/Models/Quotation.php`
- `apps/api/Domains/Attachment/Http/Resources/AttachmentResource.php`
- `apps/api/Domains/Attachment/Policies/AttachmentPolicy.php`
- `apps/api/routes/api.php`
- `apps/api/storage/openapi/openapi.json`
- `packages/api-client/src/generated/endpoints.ts`
- `packages/api-client/src/generated/schemas/*`

Frontend files to add:

- `apps/web/features/vendor-portal/components/vendor-quotation-upload-panel.tsx`
- `apps/web/features/vendor-portal/hooks/use-vendor-quotation.ts`
- `apps/web/features/sourcing/components/quotation-evidence-panel.tsx`
- `apps/web/features/sourcing/api/quotation-api.ts`
- `apps/web/features/sourcing/hooks/use-quotation-upload.ts`
- `apps/web/features/sourcing/types/quotation-view-model.ts`

Frontend files to modify:

- `apps/web/features/vendor-portal/api/vendor-portal-api.ts`
- `apps/web/features/vendor-portal/components/vendor-rfq-package.tsx`
- `apps/web/features/vendor-portal/mocks/vendor-portal-fixtures.ts`
- `apps/web/features/vendor-portal/mocks/vendor-portal-handlers.ts`
- `apps/web/features/vendor-portal/tests/vendor-rfq-portal.test.tsx`
- `apps/web/features/sourcing/components/rfq-invitation-panel.tsx`
- `apps/web/features/sourcing/mocks/rfq-invitation-fixtures.ts`
- `apps/web/features/sourcing/mocks/rfq-invitation-handlers.ts`
- `apps/web/features/sourcing/tests/rfq-invitations-workflow.test.tsx`

## Task 1: Backend Contract Tests First

- [ ] Add `apps/api/tests/Feature/QuotationUploadApiTest.php`.
- [ ] Use `RefreshDatabase`, `Storage::fake('local')`, `UploadedFile::fake()`, and the same tenant/RFQ/invitation helpers from `RfqInvitationPortalApiTest`.
- [ ] Cover vendor upload creating the quotation and attachment:

```php
public function test_vendor_portal_upload_creates_received_quotation_and_attachment(): void
{
    Storage::fake('local');
    [$tenant, $requester] = $this->tenantUser('requester');
    [, $buyer] = $this->tenantUser('buyer', $tenant);
    $rfq = $this->draftRfq($tenant, $requester, $buyer);
    $vendor = $this->vendor($tenant, ['name' => 'Northwind Traders']);
    $invitation = $this->invitation($tenant, $rfq, $vendor);
    $token = $this->issuePortalToken($tenant, $buyer, $invitation);

    $this->postJson(
        "/api/vendor-portal/rfq-invitations/{$token}/quotation/attachments",
        ['file' => UploadedFile::fake()->create('northwind-quote.pdf', 128, 'application/pdf')]
    )
        ->assertCreated()
        ->assertJsonPath('data.status', 'received')
        ->assertJsonPath('data.submissionSource', 'vendor_portal')
        ->assertJsonPath('data.attachments.0.filename', 'northwind-quote.pdf');

    $this->assertDatabaseHas('quotations', [
        'tenant_id' => $tenant->id,
        'rfq_id' => $rfq->id,
        'vendor_id' => $vendor->id,
        'rfq_invitation_id' => $invitation->id,
        'status' => 'received',
        'submission_source' => 'vendor_portal',
        'file_count' => 1,
    ]);
    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->id,
        'event_type' => 'quotation.attachment_uploaded',
    ]);
    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->id,
        'event_type' => 'rfq_invitation.quotation_received',
    ]);
}
```

- [ ] Cover buyer upload with `POST /api/rfq-invitations/{invitation}/quotation/attachments`, asserting `submissionSource` is `buyer_upload` and `submittedByUser` matches the buyer.
- [ ] Cover repeated upload to the same invitation, asserting the same quotation ID is returned and `file_count` increments.
- [ ] Cover `GET /api/vendor-portal/rfq-invitations/{token}/quotation`, returning `200` with `data: null` before upload and the quotation after upload.
- [ ] Cover `GET /api/rfq-invitations/{invitation}/quotation`, returning the tenant-scoped quotation for permitted buyers.
- [ ] Cover `GET /api/quotations/{quotation}/attachments`, returning only attachments for that quotation and tenant.
- [ ] Cover invalid, expired, cancelled, declined, and expired-status portal tokens returning the same safe errors as `RfqInvitationPortalApiTest`.
- [ ] Cover cross-tenant buyer access returning `404` or `403` without revealing quotation data.
- [ ] Cover invalid upload validation for empty files and disallowed extensions.
- [ ] Run the new test before implementation:

```bash
cd apps/api && php artisan test --filter=QuotationUploadApiTest
```

Expected result before implementation: tests fail because routes, columns, resources, and actions do not exist.

## Task 2: Backend Data Model

- [ ] Add migration `apps/api/database/migrations/2026_05_20_020000_extend_quotations_for_upload_capture.php`.
- [ ] Add nullable columns to `quotations`: `rfq_invitation_id`, `submission_source`, `submitted_at`, `submitted_by_user_id`, `submitted_by_vendor_contact`, `file_count`, `latest_received_at`.
- [ ] Add indexes and constraints:

```php
Schema::table('quotations', function (Blueprint $table): void {
    $table->foreignId('rfq_invitation_id')->nullable()->after('vendor_id')->constrained('rfq_invitations')->nullOnDelete();
    $table->string('submission_source')->nullable()->after('status');
    $table->timestamp('submitted_at')->nullable()->after('submission_source');
    $table->foreignId('submitted_by_user_id')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
    $table->json('submitted_by_vendor_contact')->nullable()->after('submitted_by_user_id');
    $table->unsignedInteger('file_count')->default(0)->after('submitted_by_vendor_contact');
    $table->timestamp('latest_received_at')->nullable()->after('file_count');

    $table->unique(['tenant_id', 'rfq_invitation_id'], 'quotations_tenant_invitation_unique');
    $table->index(['tenant_id', 'rfq_id', 'vendor_id'], 'quotations_tenant_rfq_vendor_index');
});
```

- [ ] Add `QuotationStatus` enum with `Draft`, `Received`, `Withdrawn`, `Superseded`. Only create and transition to `received` in this slice.
- [ ] Add `QuotationSubmissionSource` enum with `VendorPortal` and `BuyerUpload`.
- [ ] Update `Quotation.php` fillable, casts, and relationships:

```php
protected $fillable = [
    'tenant_id',
    'rfq_id',
    'vendor_id',
    'rfq_invitation_id',
    'number',
    'status',
    'submission_source',
    'submitted_at',
    'submitted_by_user_id',
    'submitted_by_vendor_contact',
    'file_count',
    'latest_received_at',
    'total_amount',
    'currency',
    'metadata',
];

protected function casts(): array
{
    return [
        'status' => QuotationStatus::class,
        'submission_source' => QuotationSubmissionSource::class,
        'submitted_at' => 'immutable_datetime',
        'submitted_by_vendor_contact' => 'array',
        'file_count' => 'integer',
        'latest_received_at' => 'immutable_datetime',
        'total_amount' => 'decimal:2',
        'metadata' => 'array',
    ];
}
```

- [ ] Add `rfqInvitation()`, `submittedByUser()`, and `attachments()` relationships to `Quotation`.
- [ ] Extend the existing `saving` guard in `Quotation.php` so `rfq_invitation_id` must belong to the same tenant and match `rfq_id` plus `vendor_id`.
- [ ] Run:

```bash
cd apps/api && php artisan test --filter=QuotationUploadApiTest
```

Expected result: failures move from missing schema/model fields to missing routes/actions.

## Task 3: Backend Actions, Resources, Routes

- [ ] Add `CreateOrRevealQuotationForInvitation`.
- [ ] Implement the action as the only place that creates the quotation for an invitation.
- [ ] Lock the invitation row and use `firstOrCreate` inside a transaction keyed by `tenant_id` and `rfq_invitation_id`.
- [ ] Generate quotation numbers as `QUOTE-{rfq number}-{vendor id}` so repeated uploads to the same invitation keep a stable human-readable number.
- [ ] Record `quotation.created` only when the quotation is first created.
- [ ] Add `StoreQuotationAttachment`, modeled after `StoreRequisitionAttachment`, but attach to `Quotation::class`.
- [ ] For buyer uploads, require the actor can view the invitation's RFQ through existing RFQ invitation policy checks.
- [ ] For vendor uploads, require the resolved portal invitation is portal-readable through `ResolveRfqInvitationPortalAccess`; no Sanctum user is required.
- [ ] Store metadata:

```php
[
    'source' => 'vendor_portal',
    'rfqInvitationId' => (string) $invitation->id,
    'rfqId' => (string) $invitation->rfq_id,
    'vendorId' => (string) $invitation->vendor_id,
]
```

- [ ] Update the quotation after each successful attachment insert: `status=received`, `submission_source`, `submitted_at` on first receipt, `latest_received_at=now()`, and `file_count` from active attachment count.
- [ ] Record `quotation.attachment_uploaded` for every uploaded file.
- [ ] Record `rfq_invitation.quotation_received` only when the first attachment transitions file count from zero to one.
- [ ] Add `QuotationResource` with this shape:

```php
return [
    'id' => (string) $quotation->id,
    'rfqId' => (string) $quotation->rfq_id,
    'vendorId' => (string) $quotation->vendor_id,
    'rfqInvitationId' => (string) $quotation->rfq_invitation_id,
    'number' => $quotation->number,
    'status' => $quotation->status->value,
    'submissionSource' => $quotation->submission_source?->value,
    'submittedAt' => $quotation->submitted_at?->toISOString(),
    'latestReceivedAt' => $quotation->latest_received_at?->toISOString(),
    'fileCount' => $quotation->file_count,
    'submittedByUser' => $quotation->submittedByUser ? [
        'id' => (string) $quotation->submittedByUser->id,
        'name' => $quotation->submittedByUser->name,
    ] : null,
    'submittedByVendorContact' => $quotation->submitted_by_vendor_contact,
    'attachments' => AttachmentResource::collection($quotation->whenLoaded('attachments')),
    'permissions' => [
        'canUploadAttachment' => $request->user()?->can('view', $quotation->rfq) ?? false,
        'canViewAttachments' => $request->user()?->can('view', $quotation->rfq) ?? false,
    ],
];
```

- [ ] Update `AttachmentResource` so `parentType` returns `quotation` for `Quotation::class`.
- [ ] Update `AttachmentPolicy` so authenticated buyers who can view the quotation's RFQ can preview/download quotation attachments.
- [ ] Add `RfqInvitationQuotationController` with `show`, `storeAttachment`, and `attachments` methods for authenticated buyers.
- [ ] Add `VendorPortalQuotationController` with `show` and `storeAttachment` methods for token-authenticated vendors.
- [ ] Register routes in `apps/api/routes/api.php`:

```php
Route::get('/vendor-portal/rfq-invitations/{token}/quotation', [VendorPortalQuotationController::class, 'show'])
    ->middleware('throttle:vendor-portal');
Route::post('/vendor-portal/rfq-invitations/{token}/quotation/attachments', [VendorPortalQuotationController::class, 'storeAttachment'])
    ->middleware('throttle:vendor-portal');

Route::middleware(['auth:sanctum', ResolveCurrentTenant::class])->group(function (): void {
    Route::get('/rfq-invitations/{invitation}/quotation', [RfqInvitationQuotationController::class, 'show']);
    Route::post('/rfq-invitations/{invitation}/quotation/attachments', [RfqInvitationQuotationController::class, 'storeAttachment']);
    Route::get('/quotations/{quotation}/attachments', [RfqInvitationQuotationController::class, 'attachments']);
});
```

- [ ] Run:

```bash
cd apps/api && php artisan test --filter=QuotationUploadApiTest
cd apps/api && php artisan test --filter=RfqInvitationPortalApiTest
```

Expected result: quotation upload tests pass and existing portal access tests still pass.

## Task 4: OpenAPI and Generated Client

- [ ] Update `apps/api/storage/openapi/openapi.json` with the five new operations:

```text
GET  /api/vendor-portal/rfq-invitations/{token}/quotation
POST /api/vendor-portal/rfq-invitations/{token}/quotation/attachments
GET  /api/rfq-invitations/{invitation}/quotation
POST /api/rfq-invitations/{invitation}/quotation/attachments
GET  /api/quotations/{quotation}/attachments
```

- [ ] Add schemas for `Quotation`, `QuotationResponse`, `QuotationNullableResponse`, and reuse existing `Attachment`, `ApiError`, `ValidationError`, `ForbiddenResponse`, `UnauthenticatedResponse`, `InvalidStateResponse`, and `TooManyRequestsResponse` schemas.
- [ ] Ensure upload request bodies use `multipart/form-data` with `file` binary and the existing attachment validation response shape.
- [ ] Generate the client:

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected result: generated endpoint helpers and schemas compile, and contract check exits successfully.

## Task 5: Vendor Portal API, MSW, and UI

- [ ] Extend `apps/web/features/vendor-portal/api/vendor-portal-api.ts` with:

```ts
export async function fetchVendorPortalQuotation(token: string): Promise<Quotation | null> {
  const response = await showVendorPortalQuotation(token);
  if (response.status !== 200) throw response.data;
  return response.data.data ?? null;
}

export async function uploadVendorPortalQuotationAttachment(token: string, file: File): Promise<Quotation> {
  const body = new FormData();
  body.append("file", file);
  const response = await storeVendorPortalQuotationAttachment(token, body);
  if (response.status !== 201) throw response.data;
  return response.data.data;
}
```

- [ ] Add `apps/web/features/vendor-portal/hooks/use-vendor-quotation.ts` with a query keyed by `["vendor-portal-quotation", token]` and a mutation that invalidates that key after upload.
- [ ] Update vendor portal MSW fixtures with:

```ts
export const vendorPortalQuotationFixture = {
  id: "quote-1",
  rfqId: "rfq-1",
  vendorId: "vendor-1",
  rfqInvitationId: "invitation-1",
  number: "QUOTE-RFQ-2026-001-1",
  status: "received",
  submissionSource: "vendor_portal",
  submittedAt: "2026-05-20T09:00:00.000Z",
  latestReceivedAt: "2026-05-20T09:00:00.000Z",
  fileCount: 1,
  submittedByUser: null,
  submittedByVendorContact: {
    name: "Vendor Contact",
    email: "vendor@example.test",
  },
  attachments: [
    {
      id: "attachment-quotation-1",
      parentType: "quotation",
      parentId: "quote-1",
      filename: "northwind-quotation.pdf",
      mimeType: "application/pdf",
      extension: "pdf",
      sizeBytes: 131072,
      previewable: true,
      uploadedBy: null,
      createdAt: "2026-05-20T09:00:00.000Z",
      permissions: {
        canPreview: false,
        canDownload: false,
        canDelete: false,
      },
    },
  ],
};
```

- [ ] Add MSW handlers for vendor quotation `GET` and upload `POST`.
- [ ] Replace the placeholder section in `VendorRfqPackage` with `VendorQuotationUploadPanel`.
- [ ] `VendorQuotationUploadPanel` must show accepted file guidance, current quotation status, existing uploaded files, pending state, validation errors, and success copy.
- [ ] Use an accessible `<input type="file">` with a visible label and a submit button; do not implement drag-and-drop in this slice.
- [ ] Add tests in `vendor-rfq-portal.test.tsx`:

```ts
it("lets a vendor upload a quotation file from the RFQ portal", async () => {
  const user = userEvent.setup();
  render(<VendorRfqInvitationPage token={validVendorPortalToken} />, { wrapper: TestProviders });

  expect(await screen.findByRole("heading", { name: "Field laptop refresh RFQ" })).toBeInTheDocument();
  await user.upload(screen.getByLabelText("Quotation file"), new File(["quote"], "northwind-quotation.pdf", { type: "application/pdf" }));
  await user.click(screen.getByRole("button", { name: "Upload quotation" }));

  expect(await screen.findByText("Quotation received")).toBeInTheDocument();
  expect(screen.getByText("northwind-quotation.pdf")).toBeInTheDocument();
});
```

- [ ] Keep invalid, expired, and unavailable token tests passing without exposing RFQ detail or upload controls.
- [ ] Run:

```bash
pnpm --filter @cognify/web test -- vendor-rfq-portal.test.tsx
```

Expected result: vendor portal upload tests pass.

## Task 6: Buyer API, MSW, and UI

- [ ] Add `apps/web/features/sourcing/api/quotation-api.ts` using generated client endpoints and `withActiveTenantHeader`.
- [ ] Add `apps/web/features/sourcing/types/quotation-view-model.ts` if the generated `Quotation` shape needs UI formatting only; do not duplicate server contract types for API payloads.
- [ ] Add `apps/web/features/sourcing/hooks/use-quotation-upload.ts` with query and mutation hooks keyed by invitation ID.
- [ ] Add `QuotationEvidencePanel` and render it inside each invitation card in `RfqInvitationPanel`.
- [ ] The buyer panel must show:

```text
Quotation evidence
No quotation files received yet.
Upload buyer-received quotation
```

before upload, and after upload:

```text
Quotation received
northwind-quotation.pdf
1 file
```

- [ ] Disable buyer upload for invitation statuses outside `sent` and `acknowledged`.
- [ ] Add MSW handlers for buyer quotation `GET` and upload `POST`.
- [ ] Add tests in `rfq-invitations-workflow.test.tsx`:

```ts
it("lets a buyer upload a quotation file to an RFQ invitation", async () => {
  const user = userEvent.setup();
  render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

  const heading = await screen.findByRole("heading", { name: "Northwind Traders" });
  const invitationCard = heading.closest("[data-testid='rfq-invitation-card']");
  expect(invitationCard).not.toBeNull();

  const scope = within(invitationCard as HTMLElement);
  await user.upload(scope.getByLabelText("Buyer-received quotation file"), new File(["quote"], "northwind-quotation.pdf", { type: "application/pdf" }));
  await user.click(scope.getByRole("button", { name: "Upload buyer-received quotation" }));

  expect(await scope.findByText("Quotation received")).toBeInTheDocument();
  expect(scope.getByText("northwind-quotation.pdf")).toBeInTheDocument();
});
```

- [ ] Ensure `RfqInvitationPanel` still handles invite, resend, status update, cancel, and portal-link generation tests.
- [ ] Run:

```bash
pnpm --filter @cognify/web test -- rfq-invitations-workflow.test.tsx
```

Expected result: buyer invitation tests pass.

## Task 7: Integration Verification

- [ ] Run focused backend tests:

```bash
cd apps/api && php artisan test --filter=QuotationUploadApiTest
cd apps/api && php artisan test --filter=RfqInvitationPortalApiTest
```

- [ ] Run focused frontend tests:

```bash
pnpm --filter @cognify/web test -- vendor-rfq-portal.test.tsx
pnpm --filter @cognify/web test -- rfq-invitations-workflow.test.tsx
```

- [ ] Run contract and type checks:

```bash
pnpm check:api-contract
pnpm --filter @cognify/web typecheck
```

- [ ] Run root build because generated client, API contracts, and app UI are touched:

```bash
pnpm build
```

- [ ] Run whitespace check:

```bash
git diff --check
```

Expected final result: all commands exit successfully.

## Task 8: Roadmap Loopback

- [ ] Confirm `docs/01-product/feature-roadmap.md` has P1-26 linked to this plan.
- [ ] Leave P1-27 and P1-28 as `Planned`; do not mark them implemented during this slice.
- [ ] If implementation completes successfully, update P1-26 status to `Fully Implemented` only after backend, frontend, generated client, and build verification pass.

## Task 9: Self-Review Checklist

- [ ] Quotation creation is centralized in `CreateOrRevealQuotationForInvitation`.
- [ ] Vendor token routes never require `X-Tenant-Id` or a Sanctum user.
- [ ] Buyer routes require `auth:sanctum` and `ResolveCurrentTenant`.
- [ ] Upload validation reuses `StoreAttachmentRequest` and `AttachmentStorage`.
- [ ] Attachments use `attachable_type = Domains\Quotation\Models\Quotation`.
- [ ] `AttachmentResource.parentType` returns `quotation` for quotation attachments.
- [ ] Audit events include `quotation.created`, `quotation.attachment_uploaded`, and `rfq_invitation.quotation_received`.
- [ ] Generated client endpoints are used by frontend code.
- [ ] UI components do not import mock fixtures directly.
- [ ] Manual entry, versioning, normalization, comparison, and award flows remain out of scope.
- [ ] Placeholder scan passes:

```bash
terms='T''ODO|T''BD|implement[ ]later|Similar[ ]to|edge[ ]cases|as[ ]appropriate'
rg -n "$terms" apps/api apps/web packages docs/superpowers/plans/2026-05-20-quotation-upload.md
```

Expected result: no matches introduced by this implementation except third-party generated comments if already present.
