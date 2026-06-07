import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import DashboardPage from "./page";

describe("dashboard page", () => {
  it("renders the operational dashboard metrics, actions, and requisition activity table", () => {
    render(<DashboardPage />);

    expect(screen.getByRole("heading", { name: "Dashboard" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "New requisition" })).toHaveAttribute(
      "href",
      "/requisitions/new",
    );
    expect(screen.getByRole("link", { name: "View requisitions" })).toHaveAttribute(
      "href",
      "/requisitions",
    );

    expect(screen.getByRole("heading", { name: "Drafts" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Submitted" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Needs attention" })).toBeInTheDocument();
    expect(screen.getAllByText("1")).toHaveLength(2);
    expect(screen.getByText("0")).toBeInTheDocument();

    const activityTable = screen.getByRole("table", { name: "Recent requisition activity" });
    expect(
      within(activityTable).getByRole("row", {
        name: /req-2026-000002.*submitted.*procurement review/i,
      }),
    ).toBeInTheDocument();
    expect(within(activityTable).getByRole("link", { name: "Open requisition REQ-2026-000002" })).toHaveAttribute(
      "href",
      "/requisitions/req-2",
    );
    expect(within(activityTable).getByText("Submitted")).toBeInTheDocument();
  });
});
