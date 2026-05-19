import type { Rfq } from "@cognify/api-client/schemas";

const buyer = { id: "buyer-1", name: "Priya Buyer", email: "priya.buyer@acme.test" };
const requester = { id: "user-1", name: "Maya Tan", email: "maya.tan@acme.test" };

export const rfqDraftFixture = {
  id: "rfq-1",
  tenantId: "1",
  number: "RFQ-2026-000001",
  title: "Field laptop refresh RFQ",
  status: "draft",
  scopeSummary: "Competitive sourcing required for the field laptop refresh.",
  responseDueAt: "2026-06-30T17:00:00.000000Z",
  responseInstructions: "Submit pricing, warranty, and delivery terms.",
  requiredDocuments: [
    { key: "company_profile", label: "Company profile", required: true },
    { key: "warranty_terms", label: "Warranty terms", required: true },
  ],
  lineItems: [
    {
      name: "Laptop",
      description: "Laptop",
      quantity: 10,
      unit: "each",
      notes: "16GB RAM minimum",
      unitOfMeasure: "each",
      estimatedUnitPrice: 3200,
      currency: "MYR",
    },
    {
      name: "Docking station",
      description: "Docking station",
      quantity: 10,
      unit: "each",
      notes: null,
      unitOfMeasure: "each",
      estimatedUnitPrice: 450,
      currency: "MYR",
    },
  ],
  evaluationNotes: "Compare warranty and lead time.",
  internalNotes: "Invite vendors in the next slice.",
  cancelReason: null,
  cancelledAt: null,
  createdAt: "2026-05-19T09:00:00.000000Z",
  updatedAt: "2026-05-19T09:00:00.000000Z",
  intakeReview: {
    id: "sourcing-4",
    status: "ready_for_rfq",
    sourcingPath: "needs_rfq",
    decisionReason: "Competitive sourcing required.",
    assignedBuyer: buyer,
  },
  requisition: {
    id: "req-4",
    number: "REQ-2026-000004",
    title: "Contract management tool renewal",
    status: "approved",
    department: "Legal",
    neededByDate: "2026-06-10",
    currency: "MYR",
    requester,
  },
  project: {
    id: "601",
    number: "PRJ-2026-000601",
    name: "Contract workflow refresh",
    status: "active",
  },
  auditSummary: [],
  permissions: {
    canUpdate: true,
    canCancel: true,
    canInviteVendors: false,
  },
} satisfies Rfq;

