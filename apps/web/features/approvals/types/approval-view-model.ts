import type {
  ApprovalPolicy as ApiApprovalPolicy,
  ApprovalPolicyVersion as ApiApprovalPolicyVersion,
  ApprovalPreview as ApiApprovalPreview,
  ApprovalPreviewContext as ApiApprovalPreviewContext,
  ApprovalPreviewStage as ApiApprovalPreviewStage,
  ApprovalPreviewWarning as ApiApprovalPreviewWarning,
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

export type ApprovalRouteApprover = {
  type: string;
  role?: string;
  userId?: string;
  label?: string;
};

export type ApprovalRouteStage = {
  name: string;
  completionRule: ApprovalCompletionRule;
  approvers: ApprovalRouteApprover[];
};

export type ApprovalRouteTemplate = {
  stages: ApprovalRouteStage[];
};

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

export type ApprovalPolicyFormValues = {
  name: string;
  description: string;
  subjectType: "requisition";
  rules: ApprovalPolicyRule[];
  routeTemplate: ApprovalRouteTemplate;
  slaRules: ApprovalSlaRule[];
};
