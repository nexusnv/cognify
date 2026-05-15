import type {
  ProjectActivity as ApiProjectActivity,
  ProjectRequisition as ApiProjectRequisition,
} from "@cognify/api-client/schemas";

export type ProjectStatus = "draft" | "active" | "on_hold" | "completed" | "cancelled";

export type UserSummary = {
  id: string;
  name: string;
  email: string;
};

export type ProjectPermissions = {
  canUpdate: boolean;
  canActivate: boolean;
  canHold: boolean;
  canResume: boolean;
  canComplete: boolean;
  canCancel: boolean;
  canLinkRequisitions: boolean;
  canUnlinkRequisitions: boolean;
  canViewActivity: boolean;
};

export type ProjectSummary = {
  estimatedRequisitionTotal: number;
  linkedRequisitionCount: number;
  draftRequisitionCount: number;
  submittedRequisitionCount: number;
  changesRequestedRequisitionCount: number;
  stoppedRequisitionCount: number;
  approvalPlaceholderCount: number;
  awardPlaceholderCount: number;
};

export type ProcurementProject = {
  id: string;
  tenantId: string;
  number: string;
  name: string;
  charter?: string;
  status: ProjectStatus;
  owner: UserSummary;
  budgetAmount?: number | null;
  currency: string;
  department?: string;
  costCenter?: string;
  targetStartDate?: string;
  targetCompletionDate?: string;
  cancelledAt?: string | null;
  cancellationReason?: string | null;
  completedAt?: string | null;
  summary: ProjectSummary;
  permissions: ProjectPermissions;
  createdAt: string;
  updatedAt: string;
};

export type ProjectListResponse = {
  data: ProcurementProject[];
  meta: {
    currentPage: number;
    perPage: number;
    total: number;
    lastPage: number;
  };
};

export type ProjectFormValues = {
  name: string;
  charter: string;
  ownerId: string;
  budgetAmount: string;
  currency: string;
  department: string;
  costCenter: string;
  targetStartDate: string;
  targetCompletionDate: string;
};

export type ProjectRequisition = ApiProjectRequisition;
export type ProjectActivity = ApiProjectActivity;

export type ProjectQuery = {
  search?: string;
  status?: ProjectStatus | "";
  ownerId?: string;
  department?: string;
  costCenter?: string;
  updatedFrom?: string;
  updatedTo?: string;
  page?: number;
  perPage?: number;
};
