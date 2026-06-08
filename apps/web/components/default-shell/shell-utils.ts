import type { TenantRole } from "@/features/identity/types/identity-view-model";

export function formatWorkspaceLabel(name: string | null | undefined): string {
  const trimmed = name?.trim() ?? "";
  return trimmed.length > 0 ? trimmed : "Operational workspace";
}

export function formatTenantRole(role: TenantRole | null | undefined): string {
  if (!role) return "Member";

  return role
    .split("_")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase())
    .join(" ");
}

export function getInitials(name: string): string {
  const parts = name
    .trim()
    .split(/\s+/)
    .filter(Boolean);

  if (parts.length === 0) return "CN";
  return parts
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join("");
}

export function isActivePath(itemHref: string, pathname: string): boolean {
  if (itemHref === "/dashboard") return pathname === "/dashboard" || pathname === "/";
  if (itemHref === "/") return pathname === "/";

  return pathname === itemHref || pathname.startsWith(`${itemHref}/`);
}
