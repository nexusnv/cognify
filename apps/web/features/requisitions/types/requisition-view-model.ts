export type RequisitionStatus = "draft" | "submitted" | "pending_approval";

export type UserSummary = {
  id: string;
  name: string;
  email: string;
};

export type RequisitionPermissions = {
  canUpdate: boolean;
  canSubmit: boolean;
  canViewActivity: boolean;
};

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

export type Requisition = {
  id: string;
  number: string;
  tenantId: string;
  title: string;
  status: RequisitionStatus;
  businessJustification: string;
  neededByDate: string;
  department?: string;
  projectId?: string;
  costCenter?: string;
  deliveryLocation?: string;
  currency: string;
  estimatedTotal: number;
  requester: UserSummary;
  lineItems: RequisitionLineItem[];
  createdAt: string;
  updatedAt: string;
  submittedAt?: string | null;
  permissions: RequisitionPermissions;
};

export type RequisitionActivityEvent = {
  id: string;
  type: "requisition.created" | "requisition.updated" | "requisition.submitted";
  message: string;
  actor: UserSummary | null;
  occurredAt: string;
  metadata?: Record<string, string | number | boolean | null>;
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
