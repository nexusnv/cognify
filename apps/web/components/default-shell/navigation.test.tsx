import { describe, expect, it } from "vitest";
import { requesterIdentity } from "@/features/identity/mocks/identity-fixtures";
import { getActiveNavigation, getVisibleNavigation, getVisibleSecondaryNavigation } from "./navigation";

describe("default shell navigation visibility", () => {
  it("denies permission-protected navigation until permissions are available", () => {
    const primaryItems = getVisibleNavigation(undefined);
    const secondaryItems = getVisibleSecondaryNavigation(undefined);

    expect(primaryItems.find((item) => item.title === "Procurement")?.implemented).toBe(false);
    expect(primaryItems.find((item) => item.title === "Procurement")?.items).toEqual([]);
    expect(primaryItems.find((item) => item.title === "Home")?.implemented).toBe(true);
    expect(secondaryItems.map((item) => item.name)).toEqual(["Approvals"]);
  });

  it("includes purchase orders under procurement when requisition access is allowed", () => {
    const primaryItems = getVisibleNavigation(requesterIdentity.permissions);
    const procurementItem = primaryItems.find((item) => item.title === "Procurement");

    expect(procurementItem?.items?.some((item) => item.title === "Purchase orders")).toBe(true);
  });

  it("includes finance invoice review when buyer permissions are allowed", () => {
    const primaryItems = getVisibleNavigation({
      ...requesterIdentity.permissions,
      canManageSourcingIntake: true,
    });
    const financeItem = primaryItems.find((item) => item.title === "Finance");

    expect(financeItem?.implemented).toBe(true);
    expect(financeItem?.items?.some((item) => item.title === "Invoice review")).toBe(true);
  });

  it("hides finance invoice review from approver permissions", () => {
    const primaryItems = getVisibleNavigation({
      ...requesterIdentity.permissions,
      canViewSubmittedRequisitions: true,
    });
    const financeItem = primaryItems.find((item) => item.title === "Finance");

    expect(financeItem?.implemented).toBe(false);
    expect(financeItem?.items?.some((item) => item.title === "Invoice review")).toBe(false);
  });
});

describe("getActiveNavigation", () => {
  it("sets isActive on the child that matches the pathname", () => {
    const itemsMock = [
      {
        title: "Finance",
        url: "/accounts-payable/invoices",
        icon: null,
        implemented: true,
        items: [
          {
            title: "Invoice review",
            url: "/accounts-payable/invoices",
            implemented: true,
          },
        ],
      },
    ];

    const activeItems = getActiveNavigation(itemsMock, "/accounts-payable/invoices");
    const subItem = activeItems[0].items?.[0];

    expect(subItem?.isActive).toBe(true);
    // Parent should also be active
    expect(activeItems[0].isActive).toBe(true);
  });

  it("does not set isActive on children that do not match the pathname", () => {
    const itemsMock = [
      {
        title: "Finance",
        url: "/accounts-payable/invoices",
        icon: null,
        implemented: true,
        items: [
          {
            title: "Invoice review",
            url: "/accounts-payable/invoices",
            implemented: true,
          },
        ],
      },
    ];

    const activeItems = getActiveNavigation(itemsMock, "/requisitions");
    const subItem = activeItems[0].items?.[0];

    expect(subItem?.isActive).toBe(false);
    expect(activeItems[0].isActive).toBe(false);
  });
});
