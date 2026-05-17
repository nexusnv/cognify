import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { describe, expect, it, vi } from "vitest";
import { RightPanelProvider } from "@/components/right-panel/right-panel-provider";
import { RightPanelRoot } from "@/components/right-panel/right-panel-root";
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

  return render(
    <QueryClientProvider client={queryClient}>
      <RightPanelProvider>
        {ui}
        <RightPanelRoot />
      </RightPanelProvider>
    </QueryClientProvider>,
  );
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

  it("lets requesters select an active project in the requisition form", async () => {
    const user = userEvent.setup();

    renderWithQuery(<RequisitionCreatePage />);

    const projectSelect = await screen.findByLabelText("Project");
    await screen.findByRole("option", { name: "PRJ-2026-000501 - Office refresh" });
    await user.selectOptions(projectSelect, "501");

    expect(projectSelect).toHaveValue("501");
  });

  it("shows inline submit validation before opening the confirmation dialog", async () => {
    const user = userEvent.setup();

    renderWithQuery(<RequisitionCreatePage />);

    await user.type(screen.getByLabelText("Title"), "Office chairs");
    await user.click(screen.getByRole("button", { name: "Submit" }));

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Complete the highlighted fields before submitting.",
    );
    expect(
      screen.getAllByText("Business justification is required before submission.")[0],
    ).toBeVisible();
    expect(
      screen.getByRole("link", { name: "Business justification is required before submission." }),
    ).toHaveAttribute("href", "#business-justification");
    expect(screen.getByText("Item name is required before submission.")).toBeVisible();
    expect(screen.queryByRole("dialog", { name: "Submit requisition?" })).not.toBeInTheDocument();
  });

  it("opens a requisition details panel from the work queue", async () => {
    const user = userEvent.setup();

    renderWithQuery(<RequisitionListPage />);

    expect(await screen.findByRole("heading", { name: "Requisitions" })).toBeInTheDocument();
    await user.click(
      await screen.findByRole("button", { name: "Open details panel for REQ-2026-000001" }),
    );

    const panel = screen.getByRole("dialog", { name: "Field laptop refresh" });
    expect(panel).toBeInTheDocument();
    expect(within(panel).getByText("REQ-2026-000001")).toBeInTheDocument();
    expect(within(panel).getByText("Requester")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Open workspace" })).toHaveAttribute(
      "href",
      "/requisitions/req-1",
    );
  });

  it("sorts the work queue when a sortable heading changes", async () => {
    const user = userEvent.setup();

    renderWithQuery(<RequisitionListPage />);

    const table = await screen.findByRole("table", { name: "Requisitions" });
    let rows = within(table).getAllByRole("row");
    expect(rows[1]).toHaveTextContent("Cancelled courier contract");
    expect(rows[2]).toHaveTextContent("Field laptop refresh");

    await user.click(screen.getByRole("button", { name: "Sort by Title descending" }));

    rows = within(table).getAllByRole("row");
    expect(rows[1]).toHaveTextContent("Withdrawn printer refresh");
    expect(rows[2]).toHaveTextContent("Warehouse packing supplies");
  });

  it("renders requisition detail inside the record workspace layout", async () => {
    const user = userEvent.setup();

    renderWithQuery(<RequisitionDetailPage requisitionId="req-1" />);

    expect(await screen.findByRole("link", { name: "Back to requisitions" })).toHaveAttribute(
      "href",
      "/requisitions",
    );
    expect(
      await screen.findByRole("heading", { name: "Field laptop refresh", level: 1 }),
    ).toBeInTheDocument();
    expect(await screen.findByRole("link", { name: /Office refresh/ })).toHaveAttribute(
      "href",
      "/projects/501",
    );
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
    expect(within(sections).getByRole("link", { name: "Evidence" })).toHaveAttribute(
      "href",
      "#evidence",
    );
    expect(within(sections).getByRole("link", { name: "Comments" })).toHaveAttribute(
      "href",
      "#comments",
    );
    expect(within(sections).getByRole("link", { name: "Activity" })).toHaveAttribute(
      "href",
      "#activity",
    );
    expect(within(sections).queryByRole("link", { name: "Readiness" })).not.toBeInTheDocument();

    const evidenceSection = document.getElementById("evidence");
    expect(evidenceSection).not.toBeNull();
    const evidence = within(evidenceSection as HTMLElement);
    expect(evidence.getByLabelText("Upload evidence")).toBeInTheDocument();
    expect(await evidence.findByText("supplier-quote.pdf")).toBeInTheDocument();
    await user.click(evidence.getByLabelText("Preview supplier-quote.pdf"));
    const panel = await screen.findByRole("dialog", { name: "supplier-quote.pdf" });
    expect(within(panel).getByTitle("Preview of supplier-quote.pdf")).toBeInTheDocument();

    expect(screen.getByRole("complementary", { name: "Record sidebar" })).toHaveTextContent(
      "Approval readiness",
    );
    expect(screen.getByRole("complementary", { name: "Record sidebar" })).toHaveTextContent(
      "Quotation readiness",
    );
  });

  it("shows correction guidance and resubmits a change-requested requisition", async () => {
    const user = userEvent.setup();

    renderWithQuery(<RequisitionDetailPage requisitionId="req-changes" />);

    expect(await screen.findByRole("heading", { name: "Returned laptop request" })).toBeInTheDocument();
    expect(screen.getByText("Please clarify quantity and delivery location.")).toBeInTheDocument();
    expect(screen.getByText("lineItems")).toBeInTheDocument();
    expect(screen.getByText("deliveryLocation")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Resubmit" }));

    await waitFor(() => {
      expect(screen.getAllByText("Submitted")[0]).toBeInTheDocument();
    });
    expect(screen.getByText("Requisition resubmitted")).toBeInTheDocument();
  });

  it("requires a reason before withdrawing a requisition", async () => {
    const user = userEvent.setup();

    renderWithQuery(<RequisitionDetailPage requisitionId="req-1" />);

    await user.click(await screen.findByRole("button", { name: "Withdraw" }));
    await user.click(screen.getByRole("button", { name: "Confirm withdrawal" }));

    expect(await screen.findByText("Reason is required.")).toBeInTheDocument();
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
  }, 20000);

  it("applies a template and a line item suggestion before saving the draft", async () => {
    const user = userEvent.setup();
    let createPayload: unknown;

    server.use(
      http.post("/api/requisitions", async ({ request }) => {
        createPayload = await request.json();

        return HttpResponse.json({ data: requisitionFixtures[0] }, { status: 201 });
      }),
    );

    renderWithQuery(<RequisitionCreatePage />);

    await user.type(screen.getByLabelText("Title"), "Template-backed requisition");
    await user.click(
      await screen.findByRole("button", { name: "Fill empty fields from IT equipment" }),
    );
    await waitFor(() => {
      expect(screen.queryByRole("dialog", { name: "Apply template?" })).not.toBeInTheDocument();
    });

    expect(
      await screen.findByDisplayValue(
        "Provision or replace equipment required for business operations.",
      ),
    ).toBeInTheDocument();
    const projectSelect = await screen.findByLabelText("Project");
    await screen.findByRole("option", { name: "PRJ-2026-000501 - Office refresh" });
    await user.selectOptions(projectSelect, "501");
    expect(projectSelect).toHaveValue("501");

    await user.clear(screen.getByLabelText("Item name 1"));
    await user.type(screen.getByLabelText("Item name 1"), "Lap");
    await user.click(await screen.findByRole("button", { name: /Laptop/ }));

    expect(screen.getByLabelText("Unit 1")).toHaveValue("each");
    expect(screen.getByLabelText("Estimated unit price 1")).toHaveValue(1800);

    await user.click(screen.getByRole("button", { name: "Save draft" }));
    expect(await screen.findByText("Saved")).toBeInTheDocument();
    expect(createPayload).toMatchObject({
      projectId: "501",
    });
  }, 20000);

  it("shows accessible conflict recovery when a stale save is rejected", async () => {
    const user = userEvent.setup();

    renderWithQuery(<RequisitionCreatePage />);

    await user.type(screen.getByLabelText("Title"), "Conflict laptop refresh");
    await user.type(
      screen.getByLabelText("Business justification"),
      "Replace unsupported devices for the field team.",
    );
    await user.type(screen.getByLabelText("Needed by"), "2026-06-15");
    await user.type(screen.getByLabelText("Item name 1"), "Laptop");
    await user.click(screen.getByRole("button", { name: "Save draft" }));
    expect(await screen.findByText("Saved")).toBeInTheDocument();

    server.use(
      http.patch("/api/requisitions/:requisitionId", () =>
        HttpResponse.json(
          {
            error: {
              code: "draft_conflict",
              message: "The draft has changed since it was loaded.",
            },
          },
          { status: 409 },
        ),
      ),
    );

    await user.clear(screen.getByLabelText("Title"));
    await user.type(screen.getByLabelText("Title"), "Conflict laptop refresh updated");
    await user.click(screen.getByRole("button", { name: "Save draft" }));

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "This draft changed elsewhere.",
    );
    expect(screen.getByLabelText("Title")).toHaveValue("Conflict laptop refresh updated");
  }, 20000);

  it("warns before leaving when a draft has unsaved local edits", async () => {
    const user = userEvent.setup();

    renderWithQuery(<RequisitionCreatePage />);

    await user.type(screen.getByLabelText("Title"), "Unsaved draft");

    const event = new Event("beforeunload", { cancelable: true });
    window.dispatchEvent(event);

    expect(event.defaultPrevented).toBe(true);
  });

  it("filters the work queue by queue preset", async () => {
    const user = userEvent.setup();

    renderWithQuery(<RequisitionListPage />);

    await user.click(await screen.findByRole("button", { name: "Needs my correction" }));

    expect((await screen.findAllByText("Returned laptop request")).length).toBeGreaterThan(0);
    expect(screen.queryByText("Field laptop refresh")).not.toBeInTheDocument();
  });

  it("loads an existing requisition into the edit workflow", async () => {
    renderWithQuery(<RequisitionCreatePage requisitionId="req-changes" />);

    expect(await screen.findByDisplayValue("Returned laptop request")).toBeInTheDocument();
    expect(
      screen.getByDisplayValue("Replace unsupported devices for the buyer team."),
    ).toBeInTheDocument();
  });
});
