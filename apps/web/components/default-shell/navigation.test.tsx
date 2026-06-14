import { describe, expect, it } from "vitest";
import { requesterIdentity } from "@/features/identity/mocks/identity-fixtures";
import { getVisibleNavigation, getVisibleSecondaryNavigation } from "./navigation";

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

  it("includes finance invoice review when accounts payable access is allowed", () => {
    const primaryItems = getVisibleNavigation({
      ...requesterIdentity.permissions,
      canViewSubmittedRequisitions: true,
    });
    const financeItem = primaryItems.find((item) => item.title === "Finance");

    expect(financeItem?.implemented).toBe(true);
    expect(financeItem?.items?.some((item) => item.title === "Invoice review")).toBe(true);
  });
});
