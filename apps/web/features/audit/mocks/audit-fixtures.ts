import type { AuditEventListResponse } from "@cognify/api-client/schemas";

export const auditEventsFixture: AuditEventListResponse = {
  data: [
    {
      id: "9bfb4c6f-17a7-48c1-a4cc-0fba43b3f8f3",
      action: "requisition.submitted",
      message: "Submitted for review",
      actor: {
        id: "1",
        name: "Aisha Tan",
        email: "aisha@example.com",
      },
      subject: {
        type: "requisition",
        id: "42",
        display: "REQ-2026-000042",
      },
      metadata: {
        status: "submitted",
      },
      before: {
        status: "draft",
      },
      after: {
        status: "submitted",
      },
      occurredAt: "2026-05-12T08:20:00.000000Z",
      requestId: "req_test_123",
    },
  ],
  meta: {
    currentPage: 1,
    perPage: 25,
    total: 1,
    lastPage: 1,
  },
};
