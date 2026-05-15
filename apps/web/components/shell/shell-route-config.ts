import {
  Archive,
  Building2,
  CheckSquare,
  Activity,
  FileSearch,
  FileText,
  Gauge,
  ReceiptText,
  Scale,
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

const REQUISITION_EDIT_PATH = /^\/requisitions\/([^/]+)\/edit$/;
const REQUISITION_WORKSPACE_PATH = /^\/requisitions\/[^/]+$/;

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
      { label: "Approvals", href: "/approvals", icon: CheckSquare, implemented: false },
    ],
  },
  {
    id: "sourcing",
    label: "Sourcing",
    items: [
      { label: "Vendors", href: "/vendors", icon: Building2, implemented: false },
      { label: "Quotations", href: "/quotations", icon: ReceiptText, implemented: false },
      { label: "Comparison", href: "/comparison", icon: Scale, implemented: false },
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

  if (normalizedPathname === "/requisitions") {
    return [{ label: "Requisitions" }];
  }

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

  return [{ label: "Workspace" }];
}
