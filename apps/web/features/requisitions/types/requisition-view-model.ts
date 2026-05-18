import type {
  RequisitionIntakeOptions as GeneratedRequisitionIntakeOptions,
  RequisitionItemSuggestion as GeneratedRequisitionItemSuggestion,
  RequisitionTemplate as GeneratedRequisitionTemplate,
} from "@cognify/api-client";

export type RequisitionStatus =
  | "draft"
  | "submitted"
  | "pending_approval"
  | "changes_requested"
  | "approved"
  | "rejected"
  | "withdrawn"
  | "cancelled";

export type UserSummary = {
  id: string;
  name: string;
  email: string;
};

export type RequisitionPermissions = {
  canUpdate: boolean;
  canSubmit: boolean;
  canResubmit: boolean;
  canRequestChanges: boolean;
  canWithdraw: boolean;
  canCancel: boolean;
  canComment: boolean;
  canMention: boolean;
  canViewActivity: boolean;
};

export type RequisitionQueuePreset =
  | "my_drafts"
  | "submitted"
  | "needs_my_correction"
  | "buyer_review"
  | "stopped"
  | "all_visible";

export type CollaborationMention = {
  id: string;
  mentionedUser: UserSummary;
};

export type CollaborationComment = {
  id: string;
  subjectType: "requisition";
  subjectId: string;
  author: UserSummary;
  body: string;
  mentions: CollaborationMention[];
  createdAt: string;
  updatedAt: string;
};

export type RequisitionTemplateMode = "fill-empty" | "replace";

export type RequisitionTemplate = Omit<GeneratedRequisitionTemplate, "defaults"> & {
  defaults: Partial<RequisitionFormValues>;
};

export type RequisitionItemSuggestion = GeneratedRequisitionItemSuggestion;

export type RequisitionIntakeOptions = GeneratedRequisitionIntakeOptions;

export type RequisitionLineItem = {
  id?: string;
  name: string;
  description?: string;
  quantity: number;
  unit: string;
  estimatedUnitPrice: number;
  currency: string;
  estimatedLineTotal?: number;
};

export type RequisitionProjectSummary = {
  id: string;
  number: string;
  name: string;
  status: "draft" | "active" | "on_hold" | "completed" | "cancelled";
  owner?: UserSummary | null;
};

export type Requisition = {
  id: string;
  number: string;
  tenantId: string;
  title: string;
  status: RequisitionStatus;
  lockVersion: number;
  businessJustification: string;
  neededByDate: string;
  department?: string;
  projectId?: string;
  projectSummary?: RequisitionProjectSummary | null;
  costCenter?: string;
  deliveryLocation?: string;
  currency?: string;
  estimatedTotal: number;
  requester: UserSummary;
  lineItems: RequisitionLineItem[];
  createdAt: string;
  updatedAt: string;
  submittedAt?: string | null;
  changesRequestedAt?: string | null;
  changesRequestedBy?: UserSummary | null;
  changeRequestReason?: string | null;
  changeRequestFields?: string[];
  approvedAt?: string | null;
  approvedBy?: UserSummary | null;
  rejectedAt?: string | null;
  rejectedBy?: UserSummary | null;
  rejectionReason?: string | null;
  approvalInstanceId?: string | null;
  withdrawnAt?: string | null;
  withdrawnBy?: UserSummary | null;
  withdrawalReason?: string | null;
  cancelledAt?: string | null;
  cancelledBy?: UserSummary | null;
  cancellationReason?: string | null;
  permissions: RequisitionPermissions;
};

export type RequisitionListResponse = {
  data: Requisition[];
  meta: {
    currentPage: number;
    perPage: number;
    total: number;
    lastPage: number;
  };
};

export type RequisitionFormValues = {
  title: string;
  businessJustification: string;
  neededByDate: string;
  department: string;
  projectId: string;
  costCenter: string;
  deliveryLocation: string;
  currency: string;
  lineItems: RequisitionLineItem[];
};

export type ApiValidationError = {
  message: string;
  code: string;
  errors?: Record<string, string[]>;
};
