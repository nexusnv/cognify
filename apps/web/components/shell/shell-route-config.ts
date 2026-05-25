import {
  Archive,
  Building2,
  CheckSquare,
  ClipboardCheck,
  Activity,
  FileSearch,
  FileText,
  Gauge,
  FolderKanban,
  ReceiptText,
  UserRound,
} from "lucide-react";
import type { IdentityPermissions } from "@/features/identity/types/identity-view-model";
import type { BreadcrumbItem, ShellNavGroup } from "./shell-types";

const canUseRequisitions = (permissions: IdentityPermissions) =>
  permissions.canCreateRequisition ||
  permissions.canViewSubmittedRequisitions ||
  permissions.canUpdateOwnDraftRequisition ||
  permissions.canSubmitOwnDraftRequisition;

const canUseAudit = (permissions: IdentityPermissions) => permissions.canAccessAdmin;
const canUseSourcingIntake = (permissions: IdentityPermissions) => permissions.canManageSourcingIntake;
const canUseQuotationNormalizations = (permissions: IdentityPermissions) =>
  permissions.canReviewQuotationNormalization;

const REQUISITION_EDIT_PATH = /^\/requisitions\/([^/]+)\/edit$/;
const REQUISITION_WORKSPACE_PATH = /^\/requisitions\/[^/]+$/;
const APPROVAL_TASK_WORKSPACE_PATH = /^\/approvals\/tasks\/[^/]+$/;
const APPROVAL_POLICY_WORKSPACE_PATH = /^\/approval-policies\/[^/]+$/;
const PROJECT_WORKSPACE_EDIT_PATH = /^\/projects\/([^/]+)\/edit$/;
const PROJECT_WORKSPACE_PATH = /^\/projects\/[^/]+$/;
const RFQ_WORKSPACE_PATH = /^\/sourcing\/rfqs\/[^/]+$/;
const QUOTATION_NORMALIZATION_WORKSPACE_PATH = /^\/quotations\/normalizations\/[^/]+$/;
const QUOTATION_COMPARISON_WORKSPACE_PATH = /^\/quotations\/comparisons\/[^/]+$/;
const QUOTATION_SCORING_WORKSPACE_PATH = /^\/quotations\/scoring\/[^/]+$/;
const QUOTATION_SCORING_TEMPLATE_WORKSPACE_PATH = /^\/quotations\/scoring\/templates\/[^/]+$/;

export const shellNavGroups: ShellNavGroup[] = [
  {
    id: "work",
    label: "Work",
    items: [
      { label: "Dashboard", href: "/dashboard", icon: Gauge, implemented: true },
      {
        label: "Requisitions",
        href: "/requisitions",
        icon: FileText,
        implemented: true,
        permission: canUseRequisitions,
      },
      {
        label: "Projects",
        href: "/projects",
        icon: FolderKanban,
        implemented: true,
        permission: canUseRequisitions,
      },
      { label: "Approvals", href: "/approvals", icon: CheckSquare, implemented: true },
    ],
  },
  {
    id: "sourcing",
    label: "Sourcing",
    items: [
      {
        label: "Sourcing intake",
        href: "/sourcing/intake",
        icon: ClipboardCheck,
        implemented: true,
        permission: canUseSourcingIntake,
      },
      { label: "Vendors", href: "/vendors", icon: Building2, implemented: false },
      {
        label: "Quotations",
        href: "/quotations/normalizations",
        icon: ReceiptText,
        implemented: true,
        permission: canUseQuotationNormalizations,
      },
    ],
  },
  {
    id: "governance",
    label: "Governance",
    items: [
      { label: "Evidence", href: "/evidence", icon: Archive, implemented: false },
      {
        label: "Audit",
        href: "/audit",
        icon: FileSearch,
        implemented: false,
        permission: canUseAudit,
      },
      {
        label: "Approval policies",
        href: "/approval-policies",
        icon: CheckSquare,
        implemented: true,
        permission: canUseAudit,
      },
    ],
  },
  {
    id: "manage",
    label: "Manage",
    items: [
      {
        label: "System",
        href: "/system",
        icon: Activity,
        implemented: true,
        permission: canUseAudit,
      },
      { label: "Account", href: "/account", icon: UserRound, implemented: true },
    ],
  },
];

export function getBreadcrumbs(pathname: string): BreadcrumbItem[] {
  const normalizedPathname = pathname.replace(/\/+$/, "") || "/";

  if (normalizedPathname === "/dashboard" || normalizedPathname === "/") {
    return [{ label: "Dashboard" }];
  }

  if (normalizedPathname === "/account") {
    return [{ label: "Account" }];
  }

  if (normalizedPathname === "/system") {
    return [{ label: "System" }];
  }

  if (normalizedPathname === "/approval-policies") {
    return [{ label: "Approval policies" }];
  }

  if (normalizedPathname === "/approvals") {
    return [{ label: "Approvals" }];
  }

  if (normalizedPathname === "/sourcing/intake") {
    return [{ label: "Sourcing intake" }];
  }

  if (normalizedPathname === "/quotations/normalizations") {
    return [{ label: "Quotations" }];
  }

  if (normalizedPathname === "/quotations/scoring/templates") {
    return [{ label: "Quotations", href: "/quotations/normalizations" }, { label: "Scoring Templates" }];
  }

  if (/^\/sourcing\/intake\/[^/]+$/.test(normalizedPathname)) {
    return [{ label: "Sourcing intake", href: "/sourcing/intake" }, { label: "Intake review" }];
  }

  if (RFQ_WORKSPACE_PATH.test(normalizedPathname)) {
    return [{ label: "Sourcing intake", href: "/sourcing/intake" }, { label: "RFQ draft" }];
  }

  if (QUOTATION_NORMALIZATION_WORKSPACE_PATH.test(normalizedPathname)) {
    return [
      { label: "Quotations", href: "/quotations/normalizations" },
      { label: "Normalization workspace" },
    ];
  }

  if (QUOTATION_COMPARISON_WORKSPACE_PATH.test(normalizedPathname)) {
    return [
      { label: "Quotations", href: "/quotations/normalizations" },
      { label: "Comparison workspace" },
    ];
  }

  if (QUOTATION_SCORING_TEMPLATE_WORKSPACE_PATH.test(normalizedPathname)) {
    return [
      { label: "Quotations", href: "/quotations/normalizations" },
      { label: "Scoring Templates", href: "/quotations/scoring/templates" },
      { label: "Template workspace" },
    ];
  }

  if (QUOTATION_SCORING_WORKSPACE_PATH.test(normalizedPathname)) {
    return [
      { label: "Quotations", href: "/quotations/normalizations" },
      { label: "Scoring" },
      { label: "RFQ" },
    ];
  }

  if (APPROVAL_TASK_WORKSPACE_PATH.test(normalizedPathname)) {
    return [{ label: "Approvals", href: "/approvals" }, { label: "Approval task" }];
  }

  if (normalizedPathname === "/approval-policies/new") {
    return [{ label: "Approval policies", href: "/approval-policies" }, { label: "New" }];
  }

  if (normalizedPathname === "/requisitions") {
    return [{ label: "Requisitions" }];
  }

  if (normalizedPathname === "/requisitions/new") {
    return [{ label: "Requisitions", href: "/requisitions" }, { label: "New" }];
  }

  if (normalizedPathname === "/projects") {
    return [{ label: "Projects" }];
  }

  if (normalizedPathname === "/projects/new") {
    return [{ label: "Projects", href: "/projects" }, { label: "New" }];
  }

  const requisitionEditMatch = normalizedPathname.match(REQUISITION_EDIT_PATH);
  if (requisitionEditMatch) {
    return [
      { label: "Requisitions", href: "/requisitions" },
      { label: "Requisition workspace", href: `/requisitions/${requisitionEditMatch[1]}` },
      { label: "Edit" },
    ];
  }

  if (REQUISITION_WORKSPACE_PATH.test(normalizedPathname)) {
    return [{ label: "Requisitions", href: "/requisitions" }, { label: "Requisition workspace" }];
  }

  if (APPROVAL_POLICY_WORKSPACE_PATH.test(normalizedPathname)) {
    return [{ label: "Approval policies", href: "/approval-policies" }, { label: "Policy workspace" }];
  }

  const projectEditMatch = normalizedPathname.match(PROJECT_WORKSPACE_EDIT_PATH);
  if (projectEditMatch) {
    return [
      { label: "Projects", href: "/projects" },
      { label: "Project workspace", href: `/projects/${projectEditMatch[1]}` },
      { label: "Edit" },
    ];
  }

  if (PROJECT_WORKSPACE_PATH.test(normalizedPathname)) {
    return [{ label: "Projects", href: "/projects" }, { label: "Project workspace" }];
  }

  return [{ label: "Workspace" }];
}
