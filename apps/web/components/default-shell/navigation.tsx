import type { ReactNode } from "react";
import {
  RiArchiveLine,
  RiBarChartBoxLine,
  RiCalendarLine,
  RiCheckboxCircleLine,
  RiDashboardLine,
  RiFileList3Line,
  RiFlashlightLine,
  RiFolderChartLine,
  RiFolderLine,
  RiGovernmentLine,
  RiHome5Line,
  RiInboxLine,
  RiRobot2Line,
  RiSettings3Line,
  RiShieldCheckLine,
  RiStore2Line,
  RiUserSettingsLine,
} from "@remixicon/react";
import type { IdentityPermissions } from "@/features/identity/types/identity-view-model";
import { canUseAccountsPayable, canUseCalendar, canUseRequisitions, isActivePath } from "./shell-utils";

export interface DefaultNavSubItem {
  title: string;
  url: string;
  implemented: boolean;
  isActive?: boolean;
  permission?: (permissions: IdentityPermissions) => boolean;
}

export interface DefaultNavItem {
  title: string;
  url?: string;
  icon: ReactNode;
  implemented: boolean;
  isActive?: boolean;
  permission?: (permissions: IdentityPermissions) => boolean;
  items?: DefaultNavSubItem[];
}

export interface BreadcrumbItem {
  label: string;
  href?: string;
}

const canUseSourcingIntake = (permissions: IdentityPermissions) => permissions.canManageSourcingIntake;

const canUseQuotationNormalizations = (permissions: IdentityPermissions) =>
  permissions.canReviewQuotationNormalization;

const canUseAdmin = (permissions: IdentityPermissions) => permissions.canAccessAdmin;

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

export const finalNavigationItems: DefaultNavItem[] = [
  {
    title: "Home",
    url: "/dashboard",
    icon: <RiHome5Line />,
    implemented: true,
  },
  {
    title: "My Work",
    url: "/approvals",
    icon: <RiInboxLine />,
    implemented: true,
  },
  {
    title: "Procurement",
    url: "/requisitions",
    icon: <RiFileList3Line />,
    implemented: true,
    permission: canUseRequisitions,
    items: [
      {
        title: "Requisitions",
        url: "/requisitions",
        implemented: true,
        permission: canUseRequisitions,
      },
      {
        title: "Projects",
        url: "/projects",
        implemented: true,
        permission: canUseRequisitions,
      },
      {
        title: "Purchase orders",
        url: "/purchase-orders",
        implemented: true,
        permission: canUseRequisitions,
      },
      {
        title: "Calendar",
        url: "/calendar",
        implemented: true,
        permission: canUseCalendar,
      },
      {
        title: "Sourcing intake",
        url: "/sourcing/intake",
        implemented: true,
        permission: canUseSourcingIntake,
      },
      {
        title: "Quotations",
        url: "/quotations/normalizations",
        implemented: true,
        permission: canUseQuotationNormalizations,
      },
    ],
  },
  {
    title: "Vendors",
    icon: <RiStore2Line />,
    implemented: false,
  },
  {
    title: "Finance",
    url: "/accounts-payable/invoices",
    icon: <RiBarChartBoxLine />,
    implemented: true,
    permission: canUseAccountsPayable,
    items: [
      {
        title: "Invoice review",
        url: "/accounts-payable/invoices",
        implemented: true,
        permission: canUseAccountsPayable,
      },
      {
        title: "Payment queue",
        url: "/accounts-payable/payment-queue",
        implemented: true,
        permission: canUseAccountsPayable,
      },
      {
        title: "Payment status",
        url: "/accounts-payable/payment-status",
        implemented: true,
        permission: canUseAccountsPayable,
      },
      {
        title: "Payment import",
        url: "/accounts-payable/payment-import",
        implemented: true,
        permission: canUseAccountsPayable,
      },
      {
        title: "Credit memos",
        url: "/accounts-payable/credit-memos",
        implemented: true,
        permission: canUseAccountsPayable,
      },
    ],
  },
  {
    title: "Evidence",
    icon: <RiArchiveLine />,
    implemented: false,
  },
  {
    title: "Analytics",
    icon: <RiDashboardLine />,
    implemented: false,
  },
  {
    title: "Governance",
    url: "/approval-policies",
    icon: <RiShieldCheckLine />,
    implemented: true,
    permission: canUseAdmin,
  },
  {
    title: "AI Assistant",
    icon: <RiRobot2Line />,
    implemented: false,
  },
  {
    title: "Admin",
    url: "/system",
    icon: <RiUserSettingsLine />,
    implemented: true,
    permission: canUseAdmin,
    items: [
      {
        title: "System",
        url: "/system",
        implemented: true,
        permission: canUseAdmin,
      },
      {
        title: "Approval policies",
        url: "/approval-policies",
        implemented: true,
        permission: canUseAdmin,
      },
    ],
  },
  {
    title: "Integrations",
    icon: <RiFlashlightLine />,
    implemented: false,
  },
];

export const secondaryNavigationItems = [
  {
    name: "Calendar",
    url: "/calendar",
    icon: <RiCalendarLine />,
    permission: canUseCalendar,
  },
  {
    name: "Approvals",
    url: "/approvals",
    icon: <RiCheckboxCircleLine />,
  },
  {
    name: "Projects",
    url: "/projects",
    icon: <RiFolderLine />,
    permission: canUseRequisitions,
  },
  {
    name: "System",
    url: "/system",
    icon: <RiSettings3Line />,
    permission: canUseAdmin,
  },
  {
    name: "Policies",
    url: "/approval-policies",
    icon: <RiGovernmentLine />,
    permission: canUseAdmin,
  },
  {
    name: "Sourcing intake",
    url: "/sourcing/intake",
    icon: <RiFolderChartLine />,
    permission: canUseSourcingIntake,
  },
];

export function getVisibleNavigation(
  permissions: IdentityPermissions | undefined,
): DefaultNavItem[] {
  return finalNavigationItems
    .map((item) => ({
      ...item,
      implemented:
        item.implemented && (item.permission ? Boolean(permissions && item.permission(permissions)) : true),
      items: item.items?.filter((subItem) =>
        Boolean(subItem.permission && permissions && subItem.permission(permissions)),
      ),
    }));
}

export function getVisibleSecondaryNavigation(permissions: IdentityPermissions | undefined) {
  return secondaryNavigationItems.filter((item) =>
    item.permission ? Boolean(permissions && item.permission(permissions)) : true,
  );
}

export function getActiveNavigation(items: DefaultNavItem[], pathname: string): DefaultNavItem[] {
  return items.map((item) => {
    const itemActive = item.url ? isActivePath(item.url, pathname) : false;
    const childActive = item.items?.some((subItem) => isActivePath(subItem.url, pathname)) ?? false;

    return {
      ...item,
      isActive: itemActive || childActive,
      items: item.items?.map((subItem) => ({
        ...subItem,
        isActive: isActivePath(subItem.url, pathname),
      })),
    };
  });
}

export function getBreadcrumbs(pathname: string): BreadcrumbItem[] {
  const normalizedPathname = pathname.replace(/\/+$/, "") || "/";

  if (normalizedPathname === "/dashboard" || normalizedPathname === "/") {
    return [{ label: "Dashboard" }];
  }

  if (normalizedPathname === "/account") return [{ label: "Account" }];
  if (normalizedPathname === "/system") return [{ label: "System" }];
  if (normalizedPathname === "/approval-policies") return [{ label: "Approval policies" }];
  if (normalizedPathname === "/approvals") return [{ label: "Approvals" }];
  if (normalizedPathname === "/sourcing/intake") return [{ label: "Sourcing intake" }];
  if (normalizedPathname === "/accounts-payable/invoices") return [{ label: "Finance" }, { label: "Invoice review" }];
  if (normalizedPathname === "/accounts-payable/payment-queue") return [{ label: "Finance" }, { label: "Payment queue" }];
  if (normalizedPathname === "/accounts-payable/payment-status") return [{ label: "Finance" }, { label: "Payment status" }];
  if (normalizedPathname === "/accounts-payable/payment-import") return [{ label: "Finance" }, { label: "Payment import" }];
  if (normalizedPathname === "/accounts-payable/credit-memos") return [{ label: "Finance" }, { label: "Credit memos" }];
  if (normalizedPathname === "/calendar") return [{ label: "Calendar" }];
  if (normalizedPathname === "/quotations/normalizations") return [{ label: "Quotations" }];
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
    return [{ label: "Quotations", href: "/quotations/normalizations" }, { label: "Normalization workspace" }];
  }

  if (QUOTATION_COMPARISON_WORKSPACE_PATH.test(normalizedPathname)) {
    return [{ label: "Quotations", href: "/quotations/normalizations" }, { label: "Comparison workspace" }];
  }

  if (QUOTATION_SCORING_TEMPLATE_WORKSPACE_PATH.test(normalizedPathname)) {
    return [
      { label: "Quotations", href: "/quotations/normalizations" },
      { label: "Scoring Templates", href: "/quotations/scoring/templates" },
      { label: "Template workspace" },
    ];
  }

  if (QUOTATION_SCORING_WORKSPACE_PATH.test(normalizedPathname)) {
    return [{ label: "Quotations", href: "/quotations/normalizations" }, { label: "Scoring" }, { label: "RFQ" }];
  }

  if (QUOTATION_AWARD_WORKSPACE_PATH.test(normalizedPathname)) {
    return [{ label: "Quotations", href: "/quotations/normalizations" }, { label: "Award recommendation" }];
  }

  if (APPROVAL_TASK_WORKSPACE_PATH.test(normalizedPathname)) {
    return [{ label: "Approvals", href: "/approvals" }, { label: "Approval task" }];
  }

  if (normalizedPathname === "/approval-policies/new") {
    return [{ label: "Approval policies", href: "/approval-policies" }, { label: "New" }];
  }

  if (APPROVAL_POLICY_WORKSPACE_PATH.test(normalizedPathname)) {
    return [{ label: "Approval policies", href: "/approval-policies" }, { label: "Policy workspace" }];
  }

  if (normalizedPathname === "/requisitions") return [{ label: "Requisitions" }];
  if (normalizedPathname === "/requisitions/new") {
    return [{ label: "Requisitions", href: "/requisitions" }, { label: "New" }];
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

  if (normalizedPathname === "/projects") return [{ label: "Projects" }];
  if (normalizedPathname === "/projects/new") {
    return [{ label: "Projects", href: "/projects" }, { label: "New" }];
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
