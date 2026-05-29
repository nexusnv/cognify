import type {
  ApprovalPolicy as ApiApprovalPolicy,
  ApprovalPolicyVersion as ApiApprovalPolicyVersion,
  ApprovalPreview as ApiApprovalPreview,
  ApprovalPreviewContext as ApiApprovalPreviewContext,
  ApprovalPreviewStage as ApiApprovalPreviewStage,
  ApprovalPreviewWarning as ApiApprovalPreviewWarning,
  ApprovalRouteApproverTemplate as ApiApprovalRouteApproverTemplate,
  ApprovalRouteStageTemplate as ApiApprovalRouteStageTemplate,
  ApprovalRouteTemplate as ApiApprovalRouteTemplate,
  ApprovalSummary as ApiApprovalSummary,
  ApprovalDelegation as ApiApprovalDelegation,
  ApprovalSlaSummary as ApiApprovalSlaSummary,
  ApprovalTask as ApiApprovalTask,
  ApprovalTaskQueueResponse as ApiApprovalTaskQueueResponse,
  PreviewApprovalPolicyRequest,
} from "@cognify/api-client/schemas";

export type ApprovalCompletionRule = "all" | "any";
export type ApprovalPolicyStatus = "draft" | "active" | "archived";
export type ApprovalPolicyVersionStatus = "draft" | "published" | "retired";

export type ApprovalPolicyRule = {
  field: string;
  operator: "equals" | "in" | "gte" | "lte" | "between";
  value: string | number | boolean | unknown[];
};

export type ApprovalRouteApprover = ApiApprovalRouteApproverTemplate;
export type ApprovalRouteStage = ApiApprovalRouteStageTemplate;
export type ApprovalRouteTemplate = ApiApprovalRouteTemplate;

export type ApprovalSlaRule = {
  stage: string;
  dueInHours: number;
  escalateAfterHours?: number;
};

export type ApprovalPolicyVersion = Omit<
  ApiApprovalPolicyVersion,
  "rules" | "routeTemplate" | "slaRules"
> & {
  rules: ApprovalPolicyRule[];
  routeTemplate: ApprovalRouteTemplate;
  slaRules: ApprovalSlaRule[];
};

export type ApprovalPolicy = Omit<ApiApprovalPolicy, "versions"> & {
  versions: ApprovalPolicyVersion[];
};

export type ApprovalPreview = ApiApprovalPreview;
export type ApprovalPreviewContext = ApiApprovalPreviewContext;
export type ApprovalPreviewStage = ApiApprovalPreviewStage;
export type ApprovalPreviewWarning = ApiApprovalPreviewWarning;
export type ApprovalPreviewRequest = PreviewApprovalPolicyRequest;
export type ApprovalTask = ApiApprovalTask;
export type ApprovalTaskQueueResponse = ApiApprovalTaskQueueResponse;
export type ApprovalSummary = ApiApprovalSummary;
export type ApprovalDelegation = ApiApprovalDelegation;
export type ApprovalSlaSummary = ApiApprovalSlaSummary;
export type ApprovalTaskScope =
  | "assigned_to_me"
  | "overdue"
  | "due_soon"
  | "completed_by_me"
  | "all";

export type ApprovalTaskFilters = {
  scope?: ApprovalTaskScope;
  subjectType?: "requisition" | "rfq_award_recommendation";
  status?: string;
  dueFrom?: string;
  dueTo?: string;
  requesterId?: string;
  department?: string;
  costCenter?: string;
  projectId?: string;
  amountMin?: number;
  amountMax?: number;
  updatedFrom?: string;
  updatedTo?: string;
};

export type ApprovalPolicyFormValues = {
  name: string;
  description: string;
  subjectType: "requisition" | "rfq_award_recommendation";
  rules: ApprovalPolicyRule[];
  routeTemplate: ApprovalRouteTemplate;
  slaRules: ApprovalSlaRule[];
};
