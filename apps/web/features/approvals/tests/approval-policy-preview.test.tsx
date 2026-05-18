import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { ApprovalPolicyPreview } from "../components/approval-policy-preview";
import { defaultApprovalPolicyValues } from "../schemas/approval-policy-schema";
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
    expect(screen.getByText("Missing required approval context: riskClassification, vendorId")).toBeInTheDocument();
    expect(screen.getByText("Computed preview only")).toBeInTheDocument();
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
