import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type {
  SaveRfqAwardRecommendationRequest,
} from "@cognify/api-client/schemas";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it } from "vitest";
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
