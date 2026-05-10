import type { Requisition, RequisitionActivityEvent } from "../types/requisition-view-model";

const actor = {
  id: "user-1",
  name: "Maya Tan",
  email: "maya.tan@acme.test",
};

export const requisitionFixtures: Requisition[] = [
  {
    id: "req-1",
    number: "REQ-2026-000001",
    tenantId: "tenant-1",
    title: "Field laptop refresh",
    status: "draft",
    businessJustification: "Replace unsupported devices for field buyers.",
    neededByDate: "2026-06-15",
    department: "Procurement",
    costCenter: "OPS-110",
    deliveryLocation: "Kuala Lumpur office",
    currency: "MYR",
    estimatedTotal: 7200,
    requester: actor,
    lineItems: [
      {
        id: "line-1",
        name: "Laptop",
        quantity: 4,
        unit: "each",
        estimatedUnitPrice: 1800,
        currency: "MYR",
        estimatedLineTotal: 7200,
      },
    ],
    createdAt: "2026-05-09T03:30:00.000Z",
    updatedAt: "2026-05-10T08:10:00.000Z",
    submittedAt: null,
    permissions: {
      canUpdate: true,
      canSubmit: true,
      canViewActivity: true,
    },
  },
  {
    id: "req-2",
    number: "REQ-2026-000002",
    tenantId: "tenant-1",
    title: "Warehouse packing supplies",
    status: "submitted",
    businessJustification: "Restock materials for outbound order growth.",
    neededByDate: "2026-06-01",
    department: "Operations",
    costCenter: "OPS-220",
    deliveryLocation: "Shah Alam warehouse",
    currency: "MYR",
    estimatedTotal: 3400,
    requester: actor,
    lineItems: [
      {
        id: "line-2",
        name: "Packing box bundle",
        quantity: 20,
        unit: "bundle",
        estimatedUnitPrice: 170,
        currency: "MYR",
        estimatedLineTotal: 3400,
      },
    ],
    createdAt: "2026-05-08T02:00:00.000Z",
    updatedAt: "2026-05-09T06:45:00.000Z",
    submittedAt: "2026-05-09T06:45:00.000Z",
    permissions: {
      canUpdate: false,
      canSubmit: false,
      canViewActivity: true,
    },
  },
];

export const requisitionActivityFixtures: Record<string, RequisitionActivityEvent[]> = {
  "req-1": [
    {
      id: "activity-1",
      type: "requisition.created",
      message: "Draft created",
      actor,
      occurredAt: "2026-05-09T03:30:00.000Z",
    },
    {
      id: "activity-2",
      type: "requisition.updated",
      message: "Draft updated",
      actor,
      occurredAt: "2026-05-10T08:10:00.000Z",
    },
  ],
  "req-2": [
    {
      id: "activity-3",
      type: "requisition.created",
      message: "Draft created",
      actor,
      occurredAt: "2026-05-08T02:00:00.000Z",
    },
    {
      id: "activity-4",
      type: "requisition.submitted",
      message: "Submitted for review",
      actor,
      occurredAt: "2026-05-09T06:45:00.000Z",
    },
  ],
};
