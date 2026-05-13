import type {
  IdentityPermissions,
  TenantRole,
} from "@/features/identity/types/identity-view-model";
import type { ShellNavGroup } from "./shell-types";

export function formatTenantRole(role: TenantRole | null | undefined): string {
  if (!role) return "Member";

  return role
    .split("_")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase())
    .join(" ");
}

export function isActivePath(itemHref: string, pathname: string): boolean {
  if (itemHref === "/dashboard") return pathname === "/dashboard" || pathname === "/";
  if (itemHref === "/") return pathname === "/";

  return pathname === itemHref || pathname.startsWith(`${itemHref}/`);
}

export function getVisibleNavGroups(
  groups: ShellNavGroup[],
  permissions: IdentityPermissions,
): ShellNavGroup[] {
  return groups
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => (item.permission ? item.permission(permissions) : true)),
    }))
    .filter((group) => group.items.length > 0);
}
