export type SourcingIntakeStatus =
  | "open"
  | "in_review"
  | "clarification_requested"
  | "ready_for_rfq"
  | "direct_award_recorded"
  | "closed";

export type SourcingPath =
  | "needs_rfq"
  | "needs_clarification"
  | "direct_award"
  | "no_sourcing_required";

export type SourcingIntakeChecklistItem = {
  key: string;
  label: string;
  complete: boolean;
};

export type SourcingIntakeReview = {
  id: string;
  tenantId: string;
  status: SourcingIntakeStatus;
  sourcingPath: SourcingPath | null;
  category: string | null;
  subcategory: string | null;
  urgency: "low" | "standard" | "urgent" | null;
  complexity: "low" | "medium" | "high" | null;
  targetDecisionDate: string | null;
  checklist: SourcingIntakeChecklistItem[];
  internalNotes: string | null;
  decisionReason: string | null;
  clarificationMessage: string | null;
  assignedBuyer: { id: string; name: string; email?: string | null } | null;
  requisition: {
    id: string;
    number: string;
    title: string;
    status: string;
    requester?: { id: string; name: string; email?: string | null } | null;
    department?: string | null;
    neededByDate?: string | null;
    estimatedTotal?: number | string | null;
    currency?: string | null;
  };
  project: { id: string; number: string; name: string; status: string } | null;
  permissions: {
    canClaim: boolean;
    canReassign: boolean;
    canUpdate: boolean;
    canRecordDecision: boolean;
    canClose: boolean;
    canCreateRfq: boolean;
  };
  claimedAt: string | null;
  decidedAt: string | null;
  closedAt: string | null;
  createdAt: string | null;
  updatedAt: string | null;
};

export type SourcingIntakeListResponse = {
  data: SourcingIntakeReview[];
  meta: {
    currentPage: number;
    perPage: number;
    total: number;
    statusCounts: Record<SourcingIntakeStatus | "unassigned" | "mine", number>;
  };
};

export type SourcingIntakeQuery = {
  preset?: string;
  status?: string;
  assignedBuyer?: string;
  department?: string;
  search?: string;
  sort?: string;
};
