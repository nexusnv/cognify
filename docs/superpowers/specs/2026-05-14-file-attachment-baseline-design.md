# File Attachment Baseline Design

Date: 2026-05-14
Status: Draft approved for planning
Epic: File Attachment Baseline
Release priority: P0

## 1. Purpose

This spec defines the P0 file attachment foundation for Cognify. The first product workflow is requisition evidence attachments: users can attach supporting files to a requisition, review metadata, preview safe files, download files, and remove mistakes when policy allows it.

The design keeps the first slice narrow while creating a storage and metadata boundary that can later support quotations, contracts, vendor documents, invoices, audit packs, OCR, evidence classification, and evidence search.

## 2. Product Scope

### 2.1 In Scope

- Tenant-scoped private file storage.
- Requisition evidence attachment list.
- Upload from a requisition workspace.
- Download through an authorized API endpoint.
- Preview for safe browser-supported file types.
- Attachment metadata: original filename, MIME type, size, uploader, created time, parent record, and tenant.
- File validation for size, MIME type, extension, and empty files.
- Policy-controlled delete for correcting accidental uploads.
- Audit events for upload, download, preview, and delete.
- OpenAPI contract export and generated `@cognify/api-client` consumption.
- MSW-backed frontend workflow before backend integration.

### 2.2 Out Of Scope

- OCR extraction.
- AI evidence classification.
- Annotation tools.
- Virus scanning workflow.
- Public links or external sharing.
- Direct browser-to-object-storage uploads.
- File versioning.
- Semantic evidence search.
- A standalone Evidence Vault workspace.

## 3. Workflow

The first workflow lives in the requisition workspace. A user opens a requisition, reviews an Evidence or Attachments section, uploads one or more allowed files, sees upload progress and validation errors, previews supported files, downloads files, and deletes a file if they have permission.

The UI should use the existing app-owned workflow primitives and right-panel patterns where useful. The attachment workflow is Cognify-specific and stays in `apps/web`; only generic primitive building blocks belong in `packages/ui`.

## 4. Architecture

### 4.1 Frontend

Frontend ownership:

- `apps/web/features/attachments`: shared app-level attachment API hooks, view models, upload helpers, MSW handlers, and tests.
- `apps/web/features/requisitions`: requisition-specific attachment composition.
- `apps/web/components/right-panel`: preview host integration for inline PDF and image review in the workspace panel.
- `packages/ui`: no procurement-specific attachment workflow components.

The frontend must not import mock fixtures directly into components. Components use hooks backed by MSW in tests and generated API clients in production paths.

### 4.2 Backend

Backend ownership:

- `apps/api/Domains/Attachment`: attachment model, migrations, policies, controllers, resources, actions, and tests.
- `apps/api/Domains/Requisition`: parent record authorization and route composition for requisition attachments.
- `apps/api/app`: shared storage configuration and cross-cutting request/error infrastructure only when the behavior is not domain-specific.

Files are stored on a private Laravel disk. The backend controls storage paths and never accepts a client-provided tenant, disk, or storage path.

### 4.3 API Client

The API contract is exported from Laravel OpenAPI and consumed through `packages/api-client`. App code must use generated endpoints or thin feature-owned wrappers around generated endpoints.

## 5. Data Model

Create an `attachments` table with these fields:

- `id`
- `tenant_id`
- `attachable_type`
- `attachable_id`
- `uploaded_by`
- `original_filename`
- `mime_type`
- `extension`
- `size_bytes`
- `storage_disk`
- `storage_path`
- `checksum_sha256`
- `previewable`
- `deleted_at`
- `created_at`
- `updated_at`

The initial attachable target is requisition. The polymorphic shape is intentional so the same metadata model can later support quotations, contracts, vendors, invoices, and audit packs. Authorization still flows through the parent record policy.

Storage paths should include server-derived tenant and attachment identifiers, for example:

```txt
tenants/{tenantId}/attachments/{attachmentId}/{safeFilename}
```

The path is an internal implementation detail and must not be exposed as a public URL.

## 6. API Contract

Initial endpoints:

- `GET /api/requisitions/{requisition}/attachments`
- `POST /api/requisitions/{requisition}/attachments`
- `GET /api/attachments/{attachment}/preview`
- `GET /api/attachments/{attachment}/download`
- `DELETE /api/attachments/{attachment}`

Response shape:

```json
{
  "data": {
    "id": "att_123",
    "parentType": "requisition",
    "parentId": "42",
    "filename": "supplier-quote.pdf",
    "mimeType": "application/pdf",
    "extension": "pdf",
    "sizeBytes": 42018,
    "previewable": true,
    "uploadedBy": {
      "id": "7",
      "name": "Aisha Rahman"
    },
    "createdAt": "2026-05-14T10:30:00Z"
  }
}
```

Upload uses `multipart/form-data` for P0. This is the recommended baseline because it is easier to validate, authorize, test, and audit inside Laravel. The storage service keeps an adapter boundary so direct object storage uploads can be added later without changing the product workflow.

Preview and download endpoints stream files only after tenant and parent-record authorization pass. Download sets attachment disposition. Preview sets inline disposition only for PDF, PNG, JPEG, and WebP files.

## 7. Security And Compliance

- Every query is tenant-scoped.
- Parent record access is checked before list and upload.
- Attachment access is checked through tenant and parent record policy.
- Delete is policy-controlled and soft-deletes metadata.
- File bytes remain private after delete unless physical purge is intentionally implemented later.
- Allowed MIME types and maximum size are configured server-side.
- P0 defaults allow PDF, PNG, JPEG, WebP, CSV, XLSX, DOCX, and plain text up to 25 MB per file.
- MIME type is verified server-side and not trusted from the browser.
- Original filenames are preserved as metadata but sanitized for content disposition.
- API errors use the Cognify error contract.
- Upload, preview, download, and delete emit audit events with actor, tenant, subject, and parent record.

## 8. UX Requirements

The requisition attachment surface includes:

- Empty state for no files.
- Loading state for list and upload.
- Validation error state with specific file-level messages.
- Retry path for failed list/upload operations.
- File rows with icon, filename, size, uploader, created time, and actions.
- Preview action only for previewable files.
- Download action for authorized files.
- Delete action only when policy permits it.

The UI should remain work-focused and dense enough for procurement users. It should not introduce a marketing-style evidence page or a standalone vault in P0.

## 9. Testing Strategy

Frontend tests:

- Requisition attachment list renders empty, loading, error, and populated states.
- Upload success adds a file through MSW-backed hooks.
- Validation errors are shown at file level.
- Preview/download actions call the generated wrapper paths.
- Delete is hidden or disabled when not allowed.
- Keyboard and accessible labels are present for upload and file actions.

Backend tests:

- Authenticated user can list attachments for an authorized requisition.
- Unauthorized tenant cannot list, preview, download, upload, or delete.
- Upload validates file type, size, and empty file cases.
- Download and preview stream only authorized files.
- Preview rejects non-previewable files or returns a safe error.
- Delete soft-deletes metadata and blocks future access.
- Audit events are recorded for upload, preview, download, and delete.

Contract verification:

- OpenAPI export includes all attachment endpoints and schemas.
- `packages/api-client` is regenerated.
- Web feature hooks consume generated endpoint types.

## 10. Slice Plan

Slice 1: Contract and mocked requisition workflow

- Add attachment OpenAPI design.
- Add MSW-backed web hooks and requisition attachment UI.
- Cover empty, loading, upload, preview, download, delete, and validation states in tests.

Slice 2: Backend storage and real integration

- Add Laravel attachment domain, private storage, metadata migration, policies, resources, actions, routes, and tests.
- Export OpenAPI and regenerate `@cognify/api-client`.
- Replace mocked workflow with generated client integration.
- Add audit and hardening checks.

## 11. Acceptance Criteria

- A user can upload, list, preview, download, and delete requisition evidence attachments when authorized.
- Cross-tenant access is blocked for metadata and file bytes.
- Invalid files fail with clear API and UI errors.
- File operations are audited.
- Frontend code uses MSW in tests and generated API clients for real integration.
- App-specific attachment workflow code stays in `apps/web`.
- Attachment domain logic stays in `apps/api/Domains/Attachment`.
