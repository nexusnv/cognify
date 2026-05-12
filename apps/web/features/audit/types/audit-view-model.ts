import type { AuditEvent, AuditEventListResponse } from "@cognify/api-client/schemas";

// Feature-local aliases let future audit UI add presentation fields without changing API types.
export type AuditEventViewModel = AuditEvent;
export type AuditEventListViewModel = AuditEventListResponse;
