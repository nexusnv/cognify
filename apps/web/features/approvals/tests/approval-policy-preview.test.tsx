import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { ApprovalStageMap } from "../components/approval-stage-map";
import { ApprovalPolicyPreview } from "../components/approval-policy-preview";
import { defaultApprovalPolicyValues } from "../schemas/approval-policy-schema";
import { awardApprovalPreviewFixture } from "../mocks/approval-fixtures";
import { ApprovalPolicyDetailPage } from "../workflows/approval-policy-detail-page";

function TestQueryProvider({ children }: { children: React.ReactNode }) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}

describe("ApprovalPolicyPreview", () => {
  it("renders live policy preview data without creating approval tasks", async () => {
    render(<ApprovalPolicyPreview values={defaultApprovalPolicyValues} />, {
      wrapper: TestQueryProvider,
    });

    expect(await screen.findByRole("heading", { name: "Approval preview" })).toBeInTheDocument();
    expect(await screen.findByText("Standard requisition approval")).toBeInTheDocument();
    expect(screen.getByText("Manager review")).toBeInTheDocument();
    expect(screen.getByText(/Buyer fallback/)).toBeInTheDocument();
    expect(screen.getByText("Computed preview only")).toBeInTheDocument();
    expect(screen.queryByText("Preview warnings")).not.toBeInTheDocument();
  });

  it("renders award preview context with stages warnings and fallback approvers", async () => {
    render(
      <ApprovalPolicyPreview
        values={{
          ...defaultApprovalPolicyValues,
          name: "RFQ award approval",
          subjectType: "rfq_award_recommendation",
          rules: [
            { field: "recommendedAmount", operator: "gte", value: 100000 },
            { field: "riskSummaryPresent", operator: "equals", value: true },
          ],
          routeTemplate: {
            stages: [
              {
                name: "Commercial review",
                completionRule: "all",
                approvers: [{ type: "role", role: "buyer", label: "Buyer" }],
                fallbackApprovers: [{ type: "role", role: "admin", label: "Admin fallback" }],
              },
            ],
          },
          slaRules: [{ stage: "Commercial review", dueInHours: 24, escalateAfterHours: 36 }],
        }}
      />,
      { wrapper: TestQueryProvider },
    );

    expect(await screen.findByText("RFQ award approval")).toBeInTheDocument();
    expect(screen.getByText(/version 1/i)).toBeInTheDocument();
    expect(screen.getByText("Preview warnings")).toBeInTheDocument();
    expect(screen.getByText("Commercial review")).toBeInTheDocument();
    expect(screen.getByText(/Fallback: Admin fallback/)).toBeInTheDocument();
    expect(screen.getByText(/rfq_award_recommendation/)).toBeInTheDocument();
  });

  it("renders provided award preview fixtures without querying", () => {
    render(<ApprovalPolicyPreview preview={awardApprovalPreviewFixture} />, {
      wrapper: TestQueryProvider,
    });

    expect(screen.getByText("RFQ award approval")).toBeInTheDocument();
    expect(screen.getByText("recommendedAmount gte 100000 matched")).toBeInTheDocument();
    expect(screen.getByText(/Admin fallback/)).toBeInTheDocument();
  });

  it("shows empty stage state", () => {
    render(
      <ApprovalPolicyPreview
        values={{
          ...defaultApprovalPolicyValues,
          routeTemplate: { stages: [] },
          slaRules: [],
        }}
      />,
      { wrapper: TestQueryProvider },
    );

    expect(screen.getByText("No approval stages configured.")).toBeInTheDocument();
  });

  it("shows later stages as blocked and non-actionable", () => {
    render(
      <ApprovalStageMap
        stages={[
          {
            name: "Manager review",
            completionRule: "all",
            approvers: [{ type: "role", role: "approver", label: "Approver" }],
            fallbackApprovers: [{ type: "role", role: "buyer", label: "Buyer fallback" }],
            dueAt: "2026-05-19T00:00:00.000Z",
            warnings: [],
          },
          {
            name: "Finance review",
            completionRule: "all",
            approvers: [{ type: "role", role: "approver", label: "Finance approver" }],
            fallbackApprovers: [{ type: "role", role: "buyer", label: "Buyer fallback" }],
            dueAt: null,
            warnings: [],
          },
        ] as never}
      />,
    );

    expect(screen.getByText("Finance review")).toBeInTheDocument();
    expect(screen.getByText(/blocked/)).toBeInTheDocument();
    expect(screen.getByText("Blocked until the prior stage completes.")).toBeInTheDocument();
  });

  it("shows parallel completion rules and grouped approvers", () => {
    render(
      <ApprovalStageMap
        stages={[
          {
            name: "Joint review",
            completionRule: "all",
            approvers: [
              { type: "user", userId: "user-2", label: "Priya Buyer" },
              { type: "user", userId: "user-3", label: "Finance approver" },
            ],
            fallbackApprovers: [{ type: "role", role: "admin", label: "Admin fallback" }],
            dueAt: null,
            warnings: [],
          },
          {
            name: "Either buyer review",
            completionRule: "any",
            approvers: [
              { type: "user", userId: "user-2", label: "Priya Buyer" },
              { type: "user", userId: "user-4", label: "Backup buyer" },
            ],
            fallbackApprovers: [{ type: "role", role: "admin", label: "Admin fallback" }],
            dueAt: null,
            warnings: [],
          },
        ] as never}
      />,
    );

    const jointReview = screen.getByText("Joint review").closest("li");
    const eitherReview = screen.getByText("Either buyer review").closest("li");

    expect(jointReview).not.toBeNull();
    expect(eitherReview).not.toBeNull();
    expect(within(jointReview as HTMLElement).getByText("all")).toBeInTheDocument();
    expect(within(jointReview as HTMLElement).getByText(/Priya Buyer, Finance approver/)).toBeInTheDocument();
    expect(within(eitherReview as HTMLElement).getByText("any")).toBeInTheDocument();
    expect(within(eitherReview as HTMLElement).getByText(/blocked/)).toBeInTheDocument();
    expect(within(eitherReview as HTMLElement).getByText(/Priya Buyer, Backup buyer/)).toBeInTheDocument();
  });

  it("creates and retires policy versions through the detail workflow", async () => {
    const user = userEvent.setup();

    render(<ApprovalPolicyDetailPage policyId="ap-100" />, { wrapper: TestQueryProvider });

    expect(await screen.findByRole("heading", { name: "Standard requisition approval" })).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "New version draft" }));

    expect(await screen.findByText("Version 2")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Retire version 2" }));

    expect(await screen.findByText("retired")).toBeInTheDocument();
  });
});
