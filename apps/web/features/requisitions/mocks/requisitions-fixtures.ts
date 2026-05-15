import type { AuditEvent } from "@cognify/api-client/schemas";
import type {
  Requisition,
  RequisitionIntakeOptions,
  RequisitionItemSuggestion,
  RequisitionTemplate,
} from "../types/requisition-view-model";

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
    lockVersion: 0,
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
    lockVersion: 0,
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

export const requisitionActivityFixtures: Record<string, AuditEvent[]> = {
  "req-1": [
    {
      id: "activity-1",
      action: "requisition.created",
      message: "Draft created",
      actor,
      subject: { type: "requisition", id: "req-1", display: "REQ-2026-000001" },
      metadata: {},
      before: { title: "Field laptop replacement", estimatedTotal: 7000 },
      after: { title: "Field laptop refresh", estimatedTotal: 7200 },
      occurredAt: "2026-05-09T03:30:00.000Z",
      requestId: null,
    },
    {
      id: "activity-2",
      action: "requisition.updated",
      message: "Draft updated",
      actor,
      subject: { type: "requisition", id: "req-1", display: "REQ-2026-000001" },
      metadata: {},
      before: null,
      after: null,
      occurredAt: "2026-05-10T08:10:00.000Z",
      requestId: null,
    },
  ],
  "req-2": [
    {
      id: "activity-3",
      action: "requisition.created",
      message: "Draft created",
      actor,
      subject: { type: "requisition", id: "req-2", display: "REQ-2026-000002" },
      metadata: {},
      before: null,
      after: null,
      occurredAt: "2026-05-08T02:00:00.000Z",
      requestId: null,
    },
    {
      id: "activity-4",
      action: "requisition.submitted",
      message: "Submitted for review",
      actor,
      subject: { type: "requisition", id: "req-2", display: "REQ-2026-000002" },
      metadata: {},
      before: null,
      after: null,
      occurredAt: "2026-05-09T06:45:00.000Z",
      requestId: null,
    },
  ],
};

export const requisitionTemplateFixtures: RequisitionTemplate[] = [
  {
    id: "template-it-equipment",
    name: "IT equipment",
    description: "Laptop, monitor, and accessory purchases.",
    category: "it_equipment",
    defaults: {
      department: "IT",
      costCenter: "IT-210",
      businessJustification: "Provision or replace equipment required for business operations.",
      lineItems: [
        {
          name: "Laptop",
          quantity: 1,
          unit: "each",
          estimatedUnitPrice: 1800,
          currency: "MYR",
        },
      ],
    },
  },
  {
    id: "template-saas-subscription",
    name: "SaaS subscription",
    description: "Software subscription or renewal request.",
    category: "saas_subscription",
    defaults: {
      department: "IT",
      costCenter: "IT-210",
      businessJustification: "Maintain software access required for business continuity.",
      lineItems: [
        {
          name: "SaaS subscription",
          quantity: 12,
          unit: "month",
          estimatedUnitPrice: 250,
          currency: "MYR",
        },
      ],
    },
  },
];

export const requisitionItemSuggestionFixtures: RequisitionItemSuggestion[] = [
  {
    id: "suggestion-laptop",
    name: "Laptop",
    category: "it_equipment",
    unit: "each",
    estimatedUnitPrice: 1800,
    currency: "MYR",
  },
  {
    id: "suggestion-monitor",
    name: "Monitor",
    category: "it_equipment",
    unit: "each",
    estimatedUnitPrice: 700,
    currency: "MYR",
  },
  {
    id: "suggestion-saas",
    name: "SaaS subscription",
    category: "saas_subscription",
    unit: "month",
    estimatedUnitPrice: 250,
    currency: "MYR",
  },
  {
    id: "suggestion-box-bundle",
    name: "Packing box bundle",
    category: "office_supplies",
    unit: "bundle",
    estimatedUnitPrice: 170,
    currency: "MYR",
  },
];

export const requisitionIntakeOptionsFixture: RequisitionIntakeOptions = {
  departments: [{ name: "Procurement" }, { name: "IT" }, { name: "Operations" }],
  costCenters: [
    { code: "OPS-110", name: "Operations" },
    { code: "IT-210", name: "Information Technology" },
    { code: "FIN-310", name: "Finance" },
  ],
  currencies: ["MYR", "USD", "SGD"],
  units: ["each", "bundle", "month", "hour", "day"],
};
