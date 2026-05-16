import type { TenantRole } from "@/features/identity/types/identity-view-model";

export function canManageProjects(role: TenantRole | null | undefined) {
  return role === "buyer" || role === "admin";
}
