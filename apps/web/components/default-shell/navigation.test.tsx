import { describe, expect, it } from "vitest";
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
});
