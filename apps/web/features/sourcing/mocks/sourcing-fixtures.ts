import type { SourcingIntakeReview } from "../types/sourcing-view-model";

const buyer = { id: "buyer-1", name: "Priya Buyer", email: "priya.buyer@acme.test" };
const requester = { id: "user-1", name: "Maya Tan", email: "maya.tan@acme.test" };
const buyer2 = { id: "buyer-2", name: "Amina Admin", email: "amina.admin@acme.test" };

function buildReview(
  review: Pick<
    SourcingIntakeReview,
    | "id"
    | "status"
    | "sourcingPath"
    | "category"
    | "subcategory"
    | "urgency"
    | "complexity"
    | "targetDecisionDate"
    | "checklist"
    | "internalNotes"
    | "decisionReason"
    | "clarificationMessage"
    | "assignedBuyer"
    | "claimedAt"
    | "decidedAt"
    | "closedAt"
    | "createdAt"
    | "updatedAt"
  > & {
    requisition: SourcingIntakeReview["requisition"];
    project: SourcingIntakeReview["project"];
  },
): SourcingIntakeReview {
  return {
    tenantId: "1",
    permissions: {
      canClaim: review.assignedBuyer === null,
      canReassign: review.status === "in_review",
      canUpdate: review.status === "open" || review.status === "in_review",
      canRecordDecision: review.status === "in_review",
      canClose: review.status === "clarification_requested",
      canCreateRfq: review.status === "ready_for_rfq",
    },
    ...review,
  };
}

export const sourcingIntakeFixtures: SourcingIntakeReview[] = [
  buildReview({
    id: "sourcing-1",
    status: "open",
    sourcingPath: null,
    category: null,
    subcategory: null,
    urgency: null,
    complexity: null,
    targetDecisionDate: null,
    checklist: [],
    internalNotes: null,
    decisionReason: null,
    clarificationMessage: null,
    assignedBuyer: null,
    requisition: {
      id: "req-1",
      number: "REQ-2026-000001",
      title: "Field laptop refresh",
      status: "submitted",
      requester,
      department: "Procurement",
      neededByDate: "2026-06-15",
      estimatedTotal: 7200,
      currency: "MYR",
    },
    project: {
      id: "501",
      number: "PRJ-2026-000501",
      name: "Office refresh",
      status: "active",
    },
    claimedAt: null,
    decidedAt: null,
    closedAt: null,
    createdAt: "2026-05-19T02:00:00.000Z",
    updatedAt: "2026-05-19T02:00:00.000Z",
  }),
  buildReview({
    id: "sourcing-2",
    status: "in_review",
    sourcingPath: null,
    category: "hardware",
    subcategory: "laptops",
    urgency: "urgent",
    complexity: "medium",
    targetDecisionDate: "2026-05-21",
    checklist: [
      { key: "scope", label: "Scope confirmed", complete: true },
      { key: "budget", label: "Budget checked", complete: false },
    ],
    internalNotes: "Confirm replacement lead time before RFQ.",
    decisionReason: null,
    clarificationMessage: null,
    assignedBuyer: buyer,
    requisition: {
      id: "req-2",
      number: "REQ-2026-000002",
      title: "Warehouse packing supplies",
      status: "submitted",
      requester,
      department: "Operations",
      neededByDate: "2026-06-01",
      estimatedTotal: 3400,
      currency: "MYR",
    },
    project: null,
    claimedAt: "2026-05-19T03:00:00.000Z",
    decidedAt: null,
    closedAt: null,
    createdAt: "2026-05-19T02:45:00.000Z",
    updatedAt: "2026-05-19T03:15:00.000Z",
  }),
  buildReview({
    id: "sourcing-3",
    status: "clarification_requested",
    sourcingPath: "needs_clarification",
    category: "services",
    subcategory: "consulting",
    urgency: "standard",
    complexity: "high",
    targetDecisionDate: "2026-05-24",
    checklist: [
      { key: "scope", label: "Scope confirmed", complete: true },
      { key: "vendor", label: "Vendor shortlist", complete: true },
    ],
    internalNotes: "Need a clearer scope before proceeding.",
    decisionReason: "Need clarification on the service boundaries and acceptance criteria.",
    clarificationMessage: "Please clarify deliverables, timeline, and success metrics.",
    assignedBuyer: buyer,
    requisition: {
      id: "req-3",
      number: "REQ-2026-000003",
      title: "Procurement process assessment",
      status: "submitted",
      requester,
      department: "Procurement",
      neededByDate: "2026-06-20",
      estimatedTotal: "12000",
      currency: "MYR",
    },
    project: null,
    claimedAt: "2026-05-19T03:30:00.000Z",
    decidedAt: "2026-05-19T03:45:00.000Z",
    closedAt: null,
    createdAt: "2026-05-19T03:00:00.000Z",
    updatedAt: "2026-05-19T03:45:00.000Z",
  }),
  buildReview({
    id: "sourcing-4",
    status: "ready_for_rfq",
    sourcingPath: "needs_rfq",
    category: "software",
    subcategory: "licenses",
    urgency: "standard",
    complexity: "low",
    targetDecisionDate: "2026-05-22",
    checklist: [
      { key: "scope", label: "Scope confirmed", complete: true },
      { key: "budget", label: "Budget checked", complete: true },
      { key: "vendor", label: "Vendor shortlist", complete: true },
    ],
    internalNotes: "Ready to hand off to RFQ.",
    decisionReason: "This purchase requires competitive sourcing.",
    clarificationMessage: null,
    assignedBuyer: buyer2,
    requisition: {
      id: "req-4",
      number: "REQ-2026-000004",
      title: "Contract management tool renewal",
      status: "submitted",
      requester,
      department: "Legal",
      neededByDate: "2026-06-10",
      estimatedTotal: 25000,
      currency: "MYR",
    },
    project: {
      id: "601",
      number: "PRJ-2026-000601",
      name: "Contract workflow refresh",
      status: "active",
    },
    claimedAt: "2026-05-19T04:00:00.000Z",
    decidedAt: "2026-05-19T04:20:00.000Z",
    closedAt: null,
    createdAt: "2026-05-19T03:40:00.000Z",
    updatedAt: "2026-05-19T04:20:00.000Z",
  }),
  buildReview({
    id: "sourcing-5",
    status: "direct_award_recorded",
    sourcingPath: "direct_award",
    category: "services",
    subcategory: "maintenance",
    urgency: "low",
    complexity: "low",
    targetDecisionDate: "2026-05-20",
    checklist: [
      { key: "scope", label: "Scope confirmed", complete: true },
    ],
    internalNotes: "Awarded directly to incumbent provider.",
    decisionReason: "Existing supplier is the only viable source for this requirement.",
    clarificationMessage: null,
    assignedBuyer: buyer,
    requisition: {
      id: "req-5",
      number: "REQ-2026-000005",
      title: "HVAC maintenance extension",
      status: "submitted",
      requester,
      department: "Facilities",
      neededByDate: "2026-05-30",
      estimatedTotal: 4800,
      currency: "MYR",
    },
    project: null,
    claimedAt: "2026-05-19T04:30:00.000Z",
    decidedAt: "2026-05-19T04:45:00.000Z",
    closedAt: null,
    createdAt: "2026-05-19T04:00:00.000Z",
    updatedAt: "2026-05-19T04:45:00.000Z",
  }),
];
