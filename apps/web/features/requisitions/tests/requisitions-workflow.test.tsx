import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { describe, expect, it, vi } from "vitest";
import { server } from "@/tests/msw/server";
import { requisitionFixtures } from "../mocks/requisitions-fixtures";
import { RequisitionCreatePage } from "../workflows/requisition-create-page";
import { RequisitionDetailPage } from "../workflows/requisition-detail-page";
import { RequisitionListPage } from "../workflows/requisition-list-page";

function renderWithQuery(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

describe("requisitions workflow", () => {
  it("renders the MSW-backed requisition list with status badges and primary action", async () => {
    renderWithQuery(<RequisitionListPage />);

    expect(await screen.findByRole("heading", { name: "Requisitions" })).toBeInTheDocument();
    expect(await screen.findByRole("link", { name: "New requisition" })).toHaveAttribute(
      "href",
      "/requisitions/new",
    );
    expect(await screen.findAllByText("REQ-2026-000001")).not.toHaveLength(0);
    expect(screen.getAllByText("Draft")).not.toHaveLength(0);
    expect(screen.getAllByText("Submitted")).not.toHaveLength(0);
  });

  it("shows inline submit validation before opening the confirmation dialog", async () => {
    const user = userEvent.setup();

    renderWithQuery(<RequisitionCreatePage />);

    await user.type(screen.getByLabelText("Title"), "Office chairs");
    await user.click(screen.getByRole("button", { name: "Submit" }));

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Complete the highlighted fields before submitting.",
    );
    expect(screen.getByText("Business justification is required before submission.")).toBeVisible();
    expect(screen.queryByRole("dialog", { name: "Submit requisition?" })).not.toBeInTheDocument();
  });

  it("renders requisition detail inside the record workspace layout", async () => {
    renderWithQuery(<RequisitionDetailPage requisitionId="req-1" />);

    expect(await screen.findByRole("link", { name: "Back to requisitions" })).toHaveAttribute(
      "href",
      "/requisitions",
    );
    expect(
      await screen.findByRole("heading", { name: "Field laptop refresh", level: 1 }),
    ).toBeInTheDocument();
    expect(screen.getByRole("group", { name: "Record metadata" })).toHaveTextContent(
      "Estimated total",
    );
    const sections = screen.getByRole("navigation", { name: "Record sections" });
    expect(within(sections).getByRole("link", { name: "Overview" })).toHaveAttribute(
      "href",
      "#overview",
    );
    expect(within(sections).getByRole("link", { name: "Line items" })).toHaveAttribute(
      "href",
      "#line-items",
    );
    expect(within(sections).getByRole("link", { name: "Activity" })).toHaveAttribute(
      "href",
      "#activity",
    );
    expect(within(sections).queryByRole("link", { name: "Readiness" })).not.toBeInTheDocument();

    expect(screen.getByRole("complementary", { name: "Record sidebar" })).toHaveTextContent(
      "Approval readiness",
    );
  });

  it("renders duplicate unnamed line item records without duplicate React keys", async () => {
    const consoleError = vi.spyOn(console, "error").mockImplementation(() => undefined);
    const requisition = {
      ...requisitionFixtures[0],
      lineItems: [
        {
          id: undefined,
          name: "Laptop",
          quantity: 2,
          unit: "each",
          estimatedUnitPrice: 1800,
          currency: "MYR",
          estimatedLineTotal: 3600,
        },
        {
          id: undefined,
          name: "Laptop",
          quantity: 2,
          unit: "each",
          estimatedUnitPrice: 1800,
          currency: "MYR",
          estimatedLineTotal: 3600,
        },
      ],
    };

    server.use(
      http.get("/api/requisitions/req-duplicate-lines", () => {
        return HttpResponse.json({ data: requisition });
      }),
      http.get("/api/requisitions/req-duplicate-lines/activity", () => {
        return HttpResponse.json({ data: [] });
      }),
    );

    try {
      renderWithQuery(<RequisitionDetailPage requisitionId="req-duplicate-lines" />);

      expect(
        await screen.findByRole("heading", { name: "Field laptop refresh", level: 1 }),
      ).toBeInTheDocument();
      expect(screen.getAllByText("Laptop")).toHaveLength(2);
      expect(consoleError).not.toHaveBeenCalledWith(
        expect.stringContaining("Encountered two children with the same key"),
        expect.anything(),
      );
    } finally {
      consoleError.mockRestore();
    }
  });

  it("saves and submits a valid requisition through MSW", async () => {
    const user = userEvent.setup();

    renderWithQuery(<RequisitionCreatePage />);

    await user.type(screen.getByLabelText("Title"), "Field laptop refresh");
    await user.type(
      screen.getByLabelText("Business justification"),
      "Replace unsupported devices for the buyer team.",
    );
    await user.type(screen.getByLabelText("Needed by"), "2026-06-15");
    await user.type(screen.getByLabelText("Item name 1"), "Laptop");
    await user.clear(screen.getByLabelText("Quantity 1"));
    await user.type(screen.getByLabelText("Quantity 1"), "2");
    await user.type(screen.getByLabelText("Unit 1"), "each");
    await user.clear(screen.getByLabelText("Estimated unit price 1"));
    await user.type(screen.getByLabelText("Estimated unit price 1"), "1800");

    await user.click(screen.getByRole("button", { name: "Save draft" }));
    expect(await screen.findByText("Saved")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Submit" }));
    expect(await screen.findByRole("dialog", { name: "Submit requisition?" })).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Submit requisition" }));

    await waitFor(() => {
      expect(screen.getByText("Submitted")).toBeInTheDocument();
    });
    expect(screen.getByText("Requisition submitted")).toBeInTheDocument();
  });
});
