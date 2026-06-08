import type { LucideIcon } from "lucide-react";
import type { IdentityPermissions } from "@/features/identity/types/identity-view-model";

export type ShellPrimaryArea =
  | "home"
  | "my-work"
  | "procurement"
  | "vendors"
  | "finance"
  | "evidence"
  | "analytics"
  | "governance"
  | "admin"
  | "integrations"
  | "account";

export type ShellPageTemplate =
  | "dashboard"
  | "module-landing"
  | "work-queue"
  | "record-detail"
  | "form-workspace"
  | "utility";

export type ShellNavItem = {
  id: string;
  label: string;
  href: string;
  icon: LucideIcon;
  implemented: boolean;
  permission?: (permissions: IdentityPermissions) => boolean;
};

export type ShellPrimaryNavItem = ShellNavItem & {
  area: ShellPrimaryArea;
};

export type ShellSecondaryNavItem = ShellNavItem;

export type ShellNavGroup = {
  id: string;
  label: string;
  items: ShellSecondaryNavItem[];
};

export type ShellRouteContext = {
  primaryArea: ShellPrimaryArea;
  pageTemplate: ShellPageTemplate;
  secondaryGroups: ShellNavGroup[];
  hasSecondarySidebar: boolean;
};

export type BreadcrumbItem = {
  id?: string;
  label: string;
  href?: string;
};
