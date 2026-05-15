import { describe, expect, it } from "vitest";
import { requesterIdentity } from "../../features/identity/mocks/identity-fixtures";
import { formatTenantRole, getVisibleNavGroups, isActivePath } from "./shell-utils";
import { shellNavGroups } from "./shell-route-config";

describe("shell utils", () => {
  it("computes active paths for nested operational routes", () => {
    expect(isActivePath("/requisitions", "/requisitions/req-1")).toBe(true);
    expect(isActivePath("/dashboard", "/dashboard")).toBe(true);
    expect(isActivePath("/dashboard", "/dashboarding")).toBe(false);
  });

  it("formats role labels consistently", () => {
    // Covers unexpected legacy role casing defensively; generated role types are lowercase today.
    expect(formatTenantRole("TENANT_ADMIN" as unknown as Parameters<typeof formatTenantRole>[0])).toBe(
      "Tenant Admin",
    );
  });

  it("filters navigation by permissions and implementation state", () => {
    const groups = getVisibleNavGroups(shellNavGroups, requesterIdentity.permissions);
    const labels = groups.flatMap((group) => group.items.map((item) => item.label));

    expect(labels).toContain("Dashboard");
    expect(labels).toContain("Requisitions");
    expect(labels).toContain("Account");
    expect(labels).not.toContain("Audit");
  });
});
