import { describe, expect, it } from "vitest";
import { getBreadcrumbs, shellNavGroups } from "./shell-route-config";
import { getVisibleNavGroups } from "./shell-utils";
import { requesterIdentity } from "../../features/identity/mocks/identity-fixtures";

describe("shell route helpers", () => {
  it("resolves route breadcrumbs", () => {
    expect(getBreadcrumbs("/requisitions/new")).toEqual([
      { label: "Requisitions", href: "/requisitions" },
      { label: "New" },
    ]);
    expect(getBreadcrumbs("/requisitions/req-1/edit")).toEqual([
      { label: "Requisitions", href: "/requisitions" },
      { label: "Requisition workspace", href: "/requisitions/req-1" },
      { label: "Edit" },
    ]);
    expect(getBreadcrumbs("/projects/project-1/edit")).toEqual([
      { label: "Projects", href: "/projects" },
      { label: "Project workspace", href: "/projects/project-1" },
      { label: "Edit" },
    ]);
    expect(getBreadcrumbs("/sourcing/intake")).toEqual([{ label: "Sourcing intake" }]);
    expect(getBreadcrumbs("/sourcing/intake/review-1")).toEqual([
      { label: "Sourcing intake", href: "/sourcing/intake" },
      { label: "Intake review" },
    ]);
    expect(getBreadcrumbs("/system")).toEqual([{ label: "System" }]);
  });

  it("normalizes trailing slashes before resolving breadcrumbs", () => {
    expect(getBreadcrumbs("/requisitions/")).toEqual([{ label: "Requisitions" }]);
    expect(getBreadcrumbs("/dashboard/")).toEqual([{ label: "Dashboard" }]);
  });

  it("filters navigation by permissions and implementation state", () => {
    const groups = getVisibleNavGroups(shellNavGroups, requesterIdentity.permissions);
    const labels = groups.flatMap((group) => group.items.map((item) => item.label));

    expect(labels).toContain("Dashboard");
    expect(labels).toContain("Requisitions");
    expect(labels).toContain("Account");
    expect(labels).not.toContain("Audit");
    expect(labels).not.toContain("System");
    expect(labels).not.toContain("Sourcing intake");
  });

  it("shows the System nav item for admin permissions", () => {
    const groups = getVisibleNavGroups(shellNavGroups, {
      ...requesterIdentity.permissions,
      canAccessAdmin: true,
    });
    const labels = groups.flatMap((group) => group.items.map((item) => item.label));

    expect(labels).toContain("System");
  });

  it("shows sourcing intake only for sourcing permissions", () => {
    const groups = getVisibleNavGroups(shellNavGroups, {
      ...requesterIdentity.permissions,
      canManageSourcingIntake: true,
    });
    const labels = groups.flatMap((group) => group.items.map((item) => item.label));

    expect(labels).toContain("Sourcing intake");
  });
});
