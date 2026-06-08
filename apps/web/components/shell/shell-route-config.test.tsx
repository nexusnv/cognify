import { describe, expect, it } from "vitest";
import {
  getBreadcrumbs,
  getShellRouteContext,
  primaryShellNavItems,
  shellNavGroups,
} from "./shell-route-config";
import { getVisibleNavGroups } from "./shell-utils";
import { requesterIdentity } from "../../features/identity/mocks/identity-fixtures";

describe("shell route context", () => {
  it("uses primary-only dashboard navigation by default", () => {
    const context = getShellRouteContext("/dashboard", requesterIdentity.permissions);

    expect(context.primaryArea).toBe("home");
    expect(context.secondaryGroups).toEqual([]);
    expect(context.hasSecondarySidebar).toBe(false);
  });

  it("uses procurement secondary navigation for requisition routes", () => {
    const context = getShellRouteContext("/requisitions/req-1", requesterIdentity.permissions);

    expect(context.primaryArea).toBe("procurement");
    expect(context.hasSecondarySidebar).toBe(true);
    expect(context.secondaryGroups.map((group) => group.id)).toContain("procurement-work");
    expect(
      context.secondaryGroups.flatMap((group) => group.items).map((item) => item.label),
    ).toContain("Requisitions");
  });

  it("keeps unimplemented secondary links disabled instead of active links", () => {
    const context = getShellRouteContext("/requisitions", requesterIdentity.permissions);
    const purchaseOrders = context.secondaryGroups
      .flatMap((group) => group.items)
      .find((item) => item.label === "Purchase orders");

    expect(purchaseOrders).toMatchObject({
      href: "/purchase-orders",
      implemented: false,
    });
  });

  it("filters admin-only primary areas by permission", () => {
    const requesterLabels = primaryShellNavItems
      .filter((item) => (item.permission ? item.permission(requesterIdentity.permissions) : true))
      .map((item) => item.label);

    expect(requesterLabels).not.toContain("Admin");
  });

  it("preserves existing breadcrumbs while adding route context", () => {
    expect(getBreadcrumbs("/requisitions/req-1")).toEqual([
      { label: "Requisitions", href: "/requisitions" },
      { label: "Requisition workspace" },
    ]);
  });
});

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
    expect(getBreadcrumbs("/calendar")).toEqual([{ label: "Calendar" }]);
    expect(getBreadcrumbs("/sourcing/intake/review-1")).toEqual([
      { label: "Sourcing intake", href: "/sourcing/intake" },
      { label: "Intake review" },
    ]);
    expect(getBreadcrumbs("/quotations/normalizations")).toEqual([{ label: "Quotations" }]);
    expect(getBreadcrumbs("/quotations/normalizations/norm-1")).toEqual([
      { label: "Quotations", href: "/quotations/normalizations" },
      { label: "Normalization workspace" },
    ]);
    expect(getBreadcrumbs("/quotations/comparisons/rfq-1")).toEqual([
      { label: "Quotations", href: "/quotations/normalizations" },
      { label: "Comparison workspace" },
    ]);
    expect(getBreadcrumbs("/quotations/scoring/rfq-1")).toEqual([
      { label: "Quotations", href: "/quotations/normalizations" },
      { label: "Scoring" },
      { label: "RFQ" },
    ]);
    expect(getBreadcrumbs("/quotations/awards/rfq-1")).toEqual([
      { label: "Quotations", href: "/quotations/normalizations" },
      { label: "Award recommendation" },
    ]);
    expect(getBreadcrumbs("/quotations/scoring/templates")).toEqual([
      { label: "Quotations", href: "/quotations/normalizations" },
      { label: "Scoring Templates" },
    ]);
    expect(getBreadcrumbs("/quotations/scoring/templates/template-1")).toEqual([
      { label: "Quotations", href: "/quotations/normalizations" },
      { label: "Scoring Templates", href: "/quotations/scoring/templates" },
      { label: "Template workspace" },
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
    expect(labels).not.toContain("Quotations");
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

  it("shows calendar for buyer-style permissions", () => {
    const groups = getVisibleNavGroups(shellNavGroups, {
      ...requesterIdentity.permissions,
      canViewSubmittedRequisitions: true,
    });
    const labels = groups.flatMap((group) => group.items.map((item) => item.label));

    expect(labels).toContain("Calendar");
  });

  it("shows calendar for admin permissions", () => {
    const groups = getVisibleNavGroups(shellNavGroups, {
      ...requesterIdentity.permissions,
      canAccessAdmin: true,
    });
    const labels = groups.flatMap((group) => group.items.map((item) => item.label));

    expect(labels).toContain("Calendar");
  });

  it("shows calendar for approver-style permissions", () => {
    const groups = getVisibleNavGroups(shellNavGroups, {
      ...requesterIdentity.permissions,
      canReviewQuotationNormalization: true,
    });
    const labels = groups.flatMap((group) => group.items.map((item) => item.label));

    expect(labels).toContain("Calendar");
  });

  it("hides calendar for requester-only permissions", () => {
    const groups = getVisibleNavGroups(shellNavGroups, requesterIdentity.permissions);
    const labels = groups.flatMap((group) => group.items.map((item) => item.label));

    expect(labels).not.toContain("Calendar");
  });

  it("shows quotations only for normalization review permissions", () => {
    const groups = getVisibleNavGroups(shellNavGroups, {
      ...requesterIdentity.permissions,
      canReviewQuotationNormalization: true,
    });
    const labels = groups.flatMap((group) => group.items.map((item) => item.label));

    expect(labels).toContain("Quotations");
    expect(labels).not.toContain("Comparison");
  });
});
