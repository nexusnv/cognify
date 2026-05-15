import type {
  SystemStatusResponse,
} from "@cognify/api-client/schemas";

export const healthySystemStatus = {
  data: {
    status: "ok",
    environment: "local",
    service: "cognify-api",
    version: "0.1.0",
    checkedAt: "2026-05-15T00:00:00Z",
    checks: [
      {
        id: "database",
        label: "Database",
        status: "ok",
        message: "Connected",
        remediation: null,
        metadata: {},
      },
      {
        id: "storage",
        label: "Storage",
        status: "ok",
        message: "Storage read/write succeeded",
        remediation: null,
        metadata: {},
      },
      {
        id: "openapi",
        label: "OpenAPI",
        status: "ok",
        message: "OpenAPI contract is available",
        remediation: null,
        metadata: {},
      },
    ],
    demo: {
      seeded: true,
      lastSeededAt: "2026-05-15T00:00:00Z",
      counts: {
        tenants: 1,
        users: 4,
        requisitions: 3,
        vendors: 6,
        rfqs: 1,
        quotations: 1,
        approvalTasks: 1,
        awards: 1,
      },
    },
  },
} satisfies SystemStatusResponse;
