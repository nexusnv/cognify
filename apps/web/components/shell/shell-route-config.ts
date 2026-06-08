import {
  Archive,
  Building2,
  CheckSquare,
  ClipboardCheck,
  Activity,
  CalendarDays,
  FileSearch,
  FileText,
  Gauge,
  FolderKanban,
  ReceiptText,
  UserRound,
} from "lucide-react";
import type { IdentityPermissions } from "@/features/identity/types/identity-view-model";
import type {
  BreadcrumbItem,
  ShellNavGroup,
  ShellPrimaryNavItem,
  ShellRouteContext,
} from "./shell-types";
import { getVisibleNavGroups } from "./shell-utils";

const canUseRequisitions = (permissions: IdentityPermissions) =>
  permissions.canCreateRequisition ||
  permissions.canViewSubmittedRequisitions ||
  permissions.canUpdateOwnDraftRequisition ||
  permissions.canSubmitOwnDraftRequisition;

const canUseAudit = (permissions: IdentityPermissions) => permissions.canAccessAdmin;
const canUseSourcingIntake = (permissions: IdentityPermissions) => permissions.canManageSourcingIntake;
const canUseQuotationNormalizations = (permissions: IdentityPermissions) =>
  permissions.canReviewQuotationNormalization;
const canUseCalendar = (permissions: IdentityPermissions) =>
  permissions.canAccessAdmin ||
  permissions.canManageSourcingIntake ||
  permissions.canReviewQuotationNormalization ||
  permissions.canViewSubmittedRequisitions;

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
const QUOTATION_AWARD_WORKSPACE_PATH = /^\/quotations\/awards\/[^/]+$/;

const PROCUREMENT_ROUTE_PATTERNS = [
  /^\/requisitions(?:\/.*)?$/,
  /^\/projects(?:\/.*)?$/,
  /^\/sourcing(?:\/.*)?$/,
  /^\/quotations(?:\/.*)?$/,
  /^\/calendar$/,
];

export const primaryShellNavItems: ShellPrimaryNavItem[] = [
  { id: "home", area: "home", label: "Home", href: "/dashboard", icon: Gauge, implemented: true },
  {
    id: "my-work",
    area: "my-work",
    label: "My Work",
    href: "/approvals",
    icon: CheckSquare,
    implemented: true,
  },
  {
    id: "procurement",
    area: "procurement",
    label: "Procurement",
    href: "/requisitions",
    icon: FileText,
    implemented: true,
    permission: canUseRequisitions,
  },
  {
    id: "vendors",
    area: "vendors",
    label: "Vendors",
    href: "/vendors",
    icon: Building2,
    implemented: false,
  },
  {
    id: "finance",
    area: "finance",
    label: "Finance",
    href: "/finance",
    icon: ReceiptText,
    implemented: false,
  },
  {
    id: "evidence",
    area: "evidence",
    label: "Evidence",
    href: "/evidence",
    icon: Archive,
    implemented: false,
  },
  {
    id: "analytics",
    area: "analytics",
    label: "Analytics",
    href: "/analytics",
    icon: Activity,
    implemented: false,
  },
  {
    id: "governance",
    area: "governance",
    label: "Governance",
    href: "/approval-policies",
    icon: ClipboardCheck,
    implemented: true,
    permission: canUseAudit,
  },
  {
    id: "admin",
    area: "admin",
    label: "Admin",
    href: "/system",
    icon: FileSearch,
    implemented: true,
    permission: canUseAudit,
  },
  {
    id: "integrations",
    area: "integrations",
    label: "Integrations",
    href: "/integrations",
    icon: UserRound,
    implemented: false,
  },
  {
    id: "account",
    area: "account",
    label: "Account",
    href: "/account",
    icon: UserRound,
    implemented: true,
  },
];

export const procurementSecondaryNavGroups: ShellNavGroup[] = [
  {
    id: "procurement-work",
    label: "Procurement",
    items: [
      {
        id: "requisitions",
        label: "Requisitions",
        href: "/requisitions",
        icon: FileText,
        implemented: true,
        permission: canUseRequisitions,
      },
      {
        id: "projects",
        label: "Projects",
        href: "/projects",
        icon: FolderKanban,
        implemented: true,
        permission: canUseRequisitions,
      },
      {
        id: "buyer-intake",
        label: "Buyer intake",
        href: "/sourcing/intake",
        icon: ClipboardCheck,
        implemented: true,
        permission: canUseSourcingIntake,
      },
      {
        id: "calendar",
        label: "Calendar",
        href: "/calendar",
        icon: CalendarDays,
        implemented: true,
        permission: canUseCalendar,
      },
    ],
  },
  {
    id: "procurement-sourcing",
    label: "Sourcing",
    items: [
      {
        id: "quotations",
        label: "Quotations",
        href: "/quotations/normalizations",
        icon: ReceiptText,
        implemented: true,
        permission: canUseQuotationNormalizations,
      },
      {
        id: "rfqs",
        label: "RFQs",
        href: "/sourcing/rfqs",
        icon: ClipboardCheck,
        implemented: false,
      },
      {
        id: "awards",
        label: "Awards",
        href: "/quotations/awards",
        icon: CheckSquare,
        implemented: false,
      },
    ],
  },
  {
    id: "procurement-fulfillment",
    label: "Fulfillment",
    items: [
      {
        id: "purchase-orders",
        label: "Purchase orders",
        href: "/purchase-orders",
        icon: ReceiptText,
        implemented: false,
      },
      {
        id: "receiving",
        label: "Receiving",
        href: "/receiving",
        icon: ClipboardCheck,
        implemented: false,
      },
    ],
  },
];

export const shellNavGroups: ShellNavGroup[] = [
  {
    id: "work",
    label: "Work",
    items: [
      { id: "dashboard", label: "Dashboard", href: "/dashboard", icon: Gauge, implemented: true },
      {
        id: "requisitions",
        label: "Requisitions",
        href: "/requisitions",
        icon: FileText,
        implemented: true,
        permission: canUseRequisitions,
      },
      {
        id: "projects",
        label: "Projects",
        href: "/projects",
        icon: FolderKanban,
        implemented: true,
        permission: canUseRequisitions,
      },
      {
        id: "approvals",
        label: "Approvals",
        href: "/approvals",
        icon: CheckSquare,
        implemented: true,
      },
    ],
  },
  {
    id: "sourcing",
    label: "Sourcing",
    items: [
      {
        id: "calendar",
        label: "Calendar",
        href: "/calendar",
        icon: CalendarDays,
        implemented: true,
        permission: canUseCalendar,
      },
      {
        id: "sourcing-intake",
        label: "Sourcing intake",
        href: "/sourcing/intake",
        icon: ClipboardCheck,
        implemented: true,
        permission: canUseSourcingIntake,
      },
      {
        id: "vendors",
        label: "Vendors",
        href: "/vendors",
        icon: Building2,
        implemented: false,
      },
      {
        id: "quotations",
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
      {
        id: "evidence",
        label: "Evidence",
        href: "/evidence",
        icon: Archive,
        implemented: false,
      },
      {
        id: "audit",
        label: "Audit",
        href: "/audit",
        icon: FileSearch,
        implemented: false,
        permission: canUseAudit,
      },
      {
        id: "approval-policies",
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
        id: "system",
        label: "System",
        href: "/system",
        icon: Activity,
        implemented: true,
        permission: canUseAudit,
      },
      {
        id: "account",
        label: "Account",
        href: "/account",
        icon: UserRound,
        implemented: true,
      },
    ],
  },
];

function matchesAnyPattern(pathname: string, patterns: RegExp[]) {
  return patterns.some((pattern) => pattern.test(pathname));
}

export function getShellRouteContext(
  pathname: string,
  permissions: IdentityPermissions | undefined,
): ShellRouteContext {
  const normalizedPathname = pathname.replace(/\/+$/, "") || "/";

  if (matchesAnyPattern(normalizedPathname, PROCUREMENT_ROUTE_PATTERNS)) {
    const secondaryGroups = permissions
      ? getVisibleNavGroups(procurementSecondaryNavGroups, permissions)
      : [];

    return {
      primaryArea: "procurement",
      pageTemplate: normalizedPathname.includes("/new") ? "form-workspace" : "work-queue",
      secondaryGroups,
      hasSecondarySidebar: secondaryGroups.length > 0,
    };
  }

  if (normalizedPathname === "/approvals" || normalizedPathname.startsWith("/approvals/")) {
    return {
      primaryArea: "my-work",
      pageTemplate: normalizedPathname.startsWith("/approvals/tasks/")
        ? "record-detail"
        : "work-queue",
      secondaryGroups: [],
      hasSecondarySidebar: false,
    };
  }

  if (
    normalizedPathname === "/approval-policies" ||
    normalizedPathname.startsWith("/approval-policies/")
  ) {
    return {
      primaryArea: "governance",
      pageTemplate: normalizedPathname.endsWith("/new") ? "form-workspace" : "work-queue",
      secondaryGroups: [],
      hasSecondarySidebar: false,
    };
  }

  if (normalizedPathname === "/system") {
    return {
      primaryArea: "admin",
      pageTemplate: "utility",
      secondaryGroups: [],
      hasSecondarySidebar: false,
    };
  }

  if (normalizedPathname === "/account") {
    return {
      primaryArea: "account",
      pageTemplate: "utility",
      secondaryGroups: [],
      hasSecondarySidebar: false,
    };
  }

  return {
    primaryArea: "home",
    pageTemplate: "dashboard",
    secondaryGroups: [],
    hasSecondarySidebar: false,
  };
}

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

  if (normalizedPathname === "/calendar") {
    return [{ label: "Calendar" }];
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

  if (QUOTATION_AWARD_WORKSPACE_PATH.test(normalizedPathname)) {
    return [
      { label: "Quotations", href: "/quotations/normalizations" },
      { label: "Award recommendation" },
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
