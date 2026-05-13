import type { LucideIcon } from "lucide-react";
import type { IdentityPermissions } from "@/features/identity/types/identity-view-model";

export type ShellNavItem = {
  label: string;
  href: string;
  icon: LucideIcon;
  implemented: boolean;
  permission?: (permissions: IdentityPermissions) => boolean;
};

export type ShellNavGroup = {
  id: string;
  label: string;
  items: ShellNavItem[];
};

export type BreadcrumbItem = {
  id?: string;
  label: string;
  href?: string;
};
