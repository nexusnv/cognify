import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type {
  SaveRfqAwardRecommendationRequest,
} from "@cognify/api-client/schemas";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { server } from "@/tests/msw/server";
import {
  quotationAwardRecommendationHandlers,
  resetQuotationAwardRecommendationMockState,
} from "../mocks/quotation-award-recommendation-handlers";
import {
  getQuotationAwardRecommendationFixture,
  saveQuotationAwardRecommendationFixture,
} from "../mocks/quotation-award-recommendation-fixtures";
import { RfqAwardRecommendationWorkspace } from "../workflows/rfq-award-recommendation-workspace";

describe("RFQ award recommendation workspace", () => {
  beforeEach(() => {
    resetQuotationAwardRecommendationMockState();
    window.localStorage.clear();
    window.localStorage.setItem("cognify.activeTenantId", "1");
    server.use(...quotationAwardRecommendationHandlers);
  });

  it("renders context and vendor options", async () => {
    render(<RfqAwardRecommendationWorkspace rfqId="rfq-ready" />, { wrapper: TestProviders });
    expect(await screen.findByRole("heading", { name: "Award recommendation" })).toBeInTheDocument();
    expect(screen.getByLabelText("Vendor options")).toHaveTextContent("Northwind Traders");
  });

  it("saves draft recommendation", async () => {
    const user = userEvent.setup();
    render(<RfqAwardRecommendationWorkspace rfqId="rfq-ready" />, { wrapper: TestProviders });
    await screen.findByRole("heading", { name: "Award recommendation" });

    await user.click(screen.getByRole("radio", { name: /Northwind Traders/i }));
    fireEvent.change(screen.getByLabelText("Rationale"), { target: { value: "Best overall value." } });
    await user.click(screen.getByRole("button", { name: "Save draft" }));

    await waitFor(() => expect(screen.getByLabelText("Rationale")).toHaveValue("Best overall value."));
  });

  it("blocks submit when required fields are missing", async () => {
    render(<RfqAwardRecommendationWorkspace rfqId="rfq-ready" />, { wrapper: TestProviders });
    await screen.findByRole("heading", { name: "Award recommendation" });
    expect(screen.getByRole("button", { name: "Submit for approval" })).toBeDisabled();
    expect(screen.getAllByText("Select a recommended vendor before submitting.").length).toBeGreaterThan(0);
  });

  it("blocks submit when scorecard is incomplete", async () => {
    const user = userEvent.setup();
    render(<RfqAwardRecommendationWorkspace rfqId="rfq-incomplete-scorecard" />, { wrapper: TestProviders });
    await screen.findByRole("heading", { name: "Award recommendation" });

    await user.click(screen.getByRole("radio", { name: /Northwind Traders/i }));
    fireEvent.change(screen.getByLabelText("Rationale"), { target: { value: "Ready pending score completion." } });
    expect(screen.getAllByText("Scorecard must be completed before submission.").length).toBeGreaterThan(0);
    expect(screen.getByRole("button", { name: "Submit for approval" })).toBeDisabled();
  });

  it("submits a complete recommendation", async () => {
    const user = userEvent.setup();
    render(<RfqAwardRecommendationWorkspace rfqId="rfq-draft-recommendation" />, { wrapper: TestProviders });
    await screen.findByRole("heading", { name: "Award recommendation" });

    await user.click(screen.getByRole("button", { name: "Submit for approval" }));
    await screen.findByText("Recommendation is pending approval and read-only.");
  });

  it("renders pending recommendation as read-only and withdraws with reason", async () => {
    const user = userEvent.setup();
    render(<RfqAwardRecommendationWorkspace rfqId="rfq-pending-recommendation" />, { wrapper: TestProviders });
    await screen.findByRole("heading", { name: "Award recommendation" });

    expect(screen.getByLabelText("Rationale")).toBeDisabled();
    fireEvent.change(screen.getByLabelText("Withdrawal reason"), { target: { value: "Need updated commercial clarification." } });
    await user.click(screen.getByRole("button", { name: "Withdraw recommendation" }));
    await screen.findByText("Withdrawal reason: Need updated commercial clarification.");
  });

  it("shows route for approval when recommendation is pending approval", async () => {
    render(<RfqAwardRecommendationWorkspace rfqId="rfq-pending-recommendation" />, { wrapper: TestProviders });
    await screen.findByRole("heading", { name: "Award recommendation" });

    expect(screen.getByRole("region", { name: "Approval route" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Route for approval" })).toBeEnabled();
    expect(await screen.findByText("No approval route has been started.")).toBeInTheDocument();
  });

  it("routes the recommendation and shows active approval summary", async () => {
    const user = userEvent.setup();
    render(<RfqAwardRecommendationWorkspace rfqId="rfq-pending-recommendation" />, { wrapper: TestProviders });
    await screen.findByRole("heading", { name: "Award recommendation" });

    await user.click(screen.getByRole("button", { name: "Route for approval" }));

    expect(await screen.findByText("Current stage")).toBeInTheDocument();
    expect(screen.getByText("Commercial approval")).toBeInTheDocument();
    expect(screen.getByText("Priya Buyer")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Open approval task" })).toHaveAttribute("href", "/approvals/tasks/award-task-1");
  });

  it.each([
    ["rfq-approved-recommendation", "Approved"],
    ["rfq-rejected-recommendation", "Rejected"],
    ["rfq-changes-requested-recommendation", "Changes requested"],
  ])("shows %s approval outcome without operational award controls", async (rfqId, outcome) => {
    render(<RfqAwardRecommendationWorkspace rfqId={rfqId} />, { wrapper: TestProviders });
    await screen.findByRole("heading", { name: "Award recommendation" });

    expect(await screen.findByText(outcome)).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Award vendor" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Create PO handoff" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Notify vendors" })).not.toBeInTheDocument();
  });

  it("shows draft PO handoff review controls for approved recommendations", async () => {
    render(<RfqAwardRecommendationWorkspace rfqId="rfq-approved-recommendation" />, { wrapper: TestProviders });
    await screen.findByRole("heading", { name: "Award recommendation" });

    expect(await screen.findByText("POH-2026-000001")).toBeInTheDocument();
    expect(screen.getByRole("region", { name: "PO request handoff" })).toBeInTheDocument();
    expect(screen.getByLabelText("Finance note")).toBeEnabled();
    expect(screen.getByRole("button", { name: "Save handoff review" })).toBeEnabled();
    expect(screen.getByRole("button", { name: "Mark ready" })).toBeEnabled();
    expect(screen.queryByRole("button", { name: "Download JSON" })).not.toBeInTheDocument();
  });

  it("marks draft PO handoffs ready and shows export actions", async () => {
    const user = userEvent.setup();
    render(<RfqAwardRecommendationWorkspace rfqId="rfq-approved-recommendation" />, { wrapper: TestProviders });
    await screen.findByRole("region", { name: "PO request handoff" });

    await user.click(await screen.findByRole("button", { name: "Mark ready" }));

    expect(await screen.findByRole("button", { name: "Download JSON" })).toBeEnabled();
    expect(screen.getByRole("button", { name: "Download CSV" })).toBeEnabled();
    expect(screen.queryByRole("button", { name: "Save handoff review" })).not.toBeInTheDocument();
  });

  it("downloads JSON and CSV exports for ready PO handoffs", async () => {
    const user = userEvent.setup();
    const createObjectURL = vi.mocked(URL.createObjectURL);
    createObjectURL.mockClear();
    render(<RfqAwardRecommendationWorkspace rfqId="rfq-approved-recommendation" />, { wrapper: TestProviders });
    await screen.findByRole("region", { name: "PO request handoff" });

    await user.click(await screen.findByRole("button", { name: "Mark ready" }));
    await user.click(await screen.findByRole("button", { name: "Download JSON" }));
    await user.click(await screen.findByRole("button", { name: "Download CSV" }));

    await waitFor(() => expect(createObjectURL).toHaveBeenCalledTimes(2));
  });

  it("shows exported metadata and repeat export actions", async () => {
    const user = userEvent.setup();
    render(<RfqAwardRecommendationWorkspace rfqId="rfq-approved-recommendation" />, { wrapper: TestProviders });
    await screen.findByRole("region", { name: "PO request handoff" });

    await user.click(await screen.findByRole("button", { name: "Mark ready" }));
    await user.click(await screen.findByRole("button", { name: "Download JSON" }));

    expect(await screen.findByText(/Last exported/i)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Download JSON" })).toBeEnabled();
    expect(screen.getByRole("button", { name: "Download CSV" })).toBeEnabled();
  });

  it("hides export actions for cancelled PO handoffs", async () => {
    const user = userEvent.setup();
    render(<RfqAwardRecommendationWorkspace rfqId="rfq-approved-recommendation" />, { wrapper: TestProviders });
    await screen.findByRole("region", { name: "PO request handoff" });

    fireEvent.change(await screen.findByLabelText("Cancellation reason"), { target: { value: "Award superseded by a revised sourcing decision." } });
    await user.click(screen.getByRole("button", { name: "Cancel handoff" }));

    expect(await screen.findByText(/Cancelled: Award superseded/i)).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Download JSON" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Download CSV" })).not.toBeInTheDocument();
  });

  it("does not show PO handoff actions before award approval", async () => {
    render(<RfqAwardRecommendationWorkspace rfqId="rfq-pending-recommendation" />, { wrapper: TestProviders });
    await screen.findByRole("heading", { name: "Award recommendation" });

    expect(screen.queryByRole("region", { name: "PO request handoff" })).not.toBeInTheDocument();
  });

  it("shows stale lock conflict errors from PO handoff mutations", async () => {
    const user = userEvent.setup();
    server.use(
      http.patch("/api/po-handoffs/po-handoff-1", () =>
        HttpResponse.json(
          { error: { code: "invalid_state", message: "The PO handoff has changed. Reload and try again." } },
          { status: 409 },
        )),
    );
    render(<RfqAwardRecommendationWorkspace rfqId="rfq-approved-recommendation" />, { wrapper: TestProviders });
    await screen.findByRole("region", { name: "PO request handoff" });

    fireEvent.change(await screen.findByLabelText("Finance note"), { target: { value: "Route through finance control first." } });
    await user.click(screen.getByRole("button", { name: "Save handoff review" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("The PO handoff has changed. Reload and try again.");
  });

  it("shows approval route errors in the approval panel", async () => {
    const user = userEvent.setup();
    render(<RfqAwardRecommendationWorkspace rfqId="rfq-no-award-policy" />, { wrapper: TestProviders });
    await screen.findByRole("heading", { name: "Award recommendation" });

    await user.click(screen.getByRole("button", { name: "Route for approval" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("No matching approval policy");
  });

  it("allows editing and saving from withdrawn recommendation state", async () => {
    const user = userEvent.setup();
    server.use(
      http.get("/api/rfqs/rfq-withdrawn-editable/award-recommendation", () => {
        const fixture = getQuotationAwardRecommendationFixture("rfq-pending-recommendation");
        return HttpResponse.json({
          data: {
            ...fixture,
            rfq: { ...fixture?.rfq, id: "rfq-withdrawn-editable" },
            recommendation: {
              ...fixture?.recommendation,
              status: "withdrawn",
              withdrawalReason: "Initial submission withdrawn.",
            },
          },
        });
      }),
    );

    render(<RfqAwardRecommendationWorkspace rfqId="rfq-withdrawn-editable" />, { wrapper: TestProviders });
    await screen.findByRole("heading", { name: "Award recommendation" });

    const rationale = screen.getByLabelText("Rationale");
    expect(rationale).not.toBeDisabled();
    fireEvent.change(rationale, { target: { value: "Updated rationale after withdrawal." } });
    await user.click(screen.getByRole("button", { name: "Save draft" }));
    await waitFor(() => expect(screen.getByLabelText("Rationale")).toHaveValue("Updated rationale after withdrawal."));
  });

  it("renders empty state when no vendors are available", async () => {
    render(<RfqAwardRecommendationWorkspace rfqId="rfq-no-vendors" />, { wrapper: TestProviders });
    await screen.findByRole("heading", { name: "Award recommendation" });
    expect(screen.getAllByText("No vendor quotations are available for recommendation.").length).toBeGreaterThan(0);
  });

  it("preserves draft input and shows validation error when save fails", async () => {
    const user = userEvent.setup();
    server.use(
      http.put("/api/rfqs/rfq-ready/award-recommendation", () =>
        HttpResponse.json(
          { error: { code: "validation_failed", message: "Rationale is required." } },
          { status: 422 },
        )),
    );

    render(<RfqAwardRecommendationWorkspace rfqId="rfq-ready" />, { wrapper: TestProviders });
    await screen.findByRole("heading", { name: "Award recommendation" });

    await user.click(screen.getByRole("radio", { name: /Northwind Traders/i }));
    fireEvent.change(screen.getByLabelText("Rationale"), { target: { value: "Keep this rationale on failed save." } });
    await user.click(screen.getByRole("button", { name: "Save draft" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("Rationale is required.");
    expect(screen.getByLabelText("Rationale")).toHaveValue("Keep this rationale on failed save.");
  });

  it("emits updated evidence selection when saving", async () => {
    const user = userEvent.setup();
    let capturedEvidenceKeys: string[] | null = null;
    server.use(
      http.put("/api/rfqs/rfq-ready/award-recommendation", async ({ request }) => {
        const capturedPayload = await request.json() as SaveRfqAwardRecommendationRequest;
        capturedEvidenceKeys = (capturedPayload.evidenceReferences ?? []).map((item) => `${item.type}:${item.id}`);
        const next = saveQuotationAwardRecommendationFixture("rfq-ready", capturedPayload);
        return HttpResponse.json({ data: next });
      }),
    );

    render(<RfqAwardRecommendationWorkspace rfqId="rfq-ready" />, { wrapper: TestProviders });
    await screen.findByRole("heading", { name: "Award recommendation" });

    await user.click(screen.getByLabelText("Northwind evaluated quotation version"));
    await user.click(screen.getByRole("button", { name: "Save draft" }));

    await waitFor(() => expect(capturedEvidenceKeys).not.toBeNull());
    expect(capturedEvidenceKeys).not.toContain("quotation_version:301");
  });

  it("disables save and submit when award recommendation manage/submit permissions are denied", async () => {
    server.use(
      http.get("/api/rfqs/rfq-permission-denied/award-recommendation", () => {
        const fixture = getQuotationAwardRecommendationFixture("rfq-ready");
        return HttpResponse.json({
          data: {
            ...fixture,
            rfq: { ...fixture?.rfq, id: "rfq-permission-denied" },
            permissions: {
              canViewAwardRecommendation: true,
              canManageAwardRecommendation: false,
              canSubmitAwardRecommendation: false,
              canWithdrawAwardRecommendation: false,
            },
          },
        });
      }),
    );

    render(<RfqAwardRecommendationWorkspace rfqId="rfq-permission-denied" />, { wrapper: TestProviders });
    await screen.findByRole("heading", { name: "Award recommendation" });

    expect(screen.getByRole("button", { name: "Save draft" })).toBeDisabled();
    expect(screen.getByRole("button", { name: "Submit for approval" })).toBeDisabled();
    expect(screen.queryByRole("button", { name: "Withdraw recommendation" })).not.toBeInTheDocument();
  });

  it("hides withdraw action when withdraw permission is denied for pending recommendation", async () => {
    server.use(
      http.get("/api/rfqs/rfq-pending-no-withdraw/award-recommendation", () => {
        const fixture = getQuotationAwardRecommendationFixture("rfq-pending-recommendation");
        return HttpResponse.json({
          data: {
            ...fixture,
            rfq: { ...fixture?.rfq, id: "rfq-pending-no-withdraw" },
            permissions: {
              canViewAwardRecommendation: true,
              canManageAwardRecommendation: true,
              canSubmitAwardRecommendation: true,
              canWithdrawAwardRecommendation: false,
            },
          },
        });
      }),
    );

    render(<RfqAwardRecommendationWorkspace rfqId="rfq-pending-no-withdraw" />, { wrapper: TestProviders });
    await screen.findByRole("heading", { name: "Award recommendation" });
    expect(screen.queryByRole("button", { name: "Withdraw recommendation" })).not.toBeInTheDocument();
  });

  it("maps raw not_found query error payloads to workspace not-found copy", async () => {
    server.use(
      http.get("/api/rfqs/rfq-query-not-found/award-recommendation", () =>
        HttpResponse.json({ error: { code: "not_found", message: "Missing recommendation." } }, { status: 404 })),
    );

    render(<RfqAwardRecommendationWorkspace rfqId="rfq-query-not-found" />, { wrapper: TestProviders });
    expect(await screen.findByRole("alert")).toHaveTextContent("This award recommendation could not be found.");
  });
});

function TestProviders({ children }: { children: ReactNode }) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}
