import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { ReactNode } from "react";
import { describe, expect, it, vi } from "vitest";
import { ApprovalPolicyForm } from "../forms/approval-policy-form";

describe("ApprovalPolicyForm", () => {
  it("exposes policy subject rules route approvers fallback and SLA controls", () => {
    render(<ApprovalPolicyForm onSubmit={() => undefined} />, { wrapper: TestQueryProvider });

    expect(screen.getByLabelText("Subject type")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /Add rule/ })).toBeInTheDocument();
    expect(screen.getByLabelText("Stage name")).toBeInTheDocument();
    expect(screen.getByLabelText("Completion rule")).toBeInTheDocument();
    expect(screen.getByLabelText("Approver type")).toBeInTheDocument();
    expect(screen.getByLabelText("Approver role")).toBeInTheDocument();
    expect(screen.getByLabelText("Approver user ID")).toBeInTheDocument();
    expect(screen.getByLabelText("Fallback approver type")).toBeInTheDocument();
    expect(screen.getByLabelText("Fallback approver role")).toBeInTheDocument();
    expect(screen.getByLabelText("Fallback approver user ID")).toBeInTheDocument();
    expect(screen.getByLabelText("Due hours")).toBeInTheDocument();
    expect(screen.getByLabelText("Escalation hours")).toBeInTheDocument();
  });

  it("submits award policy route template and preview-ready rules", async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<ApprovalPolicyForm onSubmit={onSubmit} />, { wrapper: TestQueryProvider });

    await user.type(screen.getByLabelText("Policy name"), "Award approval route");
    await user.selectOptions(screen.getByLabelText("Subject type"), "rfq_award_recommendation");
    await user.click(screen.getByRole("button", { name: /Add rule/ }));

    const ruleRow = screen.getByLabelText("Rule 1 field").closest("div");
    expect(ruleRow).not.toBeNull();
    await user.selectOptions(within(ruleRow as HTMLElement).getByLabelText("Rule 1 field"), "recommendedAmount");
    await user.selectOptions(within(ruleRow as HTMLElement).getByLabelText("Rule 1 operator"), "gte");
    await user.clear(within(ruleRow as HTMLElement).getByLabelText("Rule 1 value"));
    await user.type(within(ruleRow as HTMLElement).getByLabelText("Rule 1 value"), "10000");

    await user.clear(screen.getByLabelText("Stage name"));
    await user.type(screen.getByLabelText("Stage name"), "Commercial review");
    await user.selectOptions(screen.getByLabelText("Completion rule"), "any");
    await user.clear(screen.getByLabelText("Approver role"));
    await user.type(screen.getByLabelText("Approver role"), "buyer");
    await user.clear(screen.getByLabelText("Approver label"));
    await user.type(screen.getByLabelText("Approver label"), "Buyer");
    await user.clear(screen.getByLabelText("Fallback approver role"));
    await user.type(screen.getByLabelText("Fallback approver role"), "admin");
    await user.clear(screen.getByLabelText("Fallback approver label"));
    await user.type(screen.getByLabelText("Fallback approver label"), "Admin fallback");
    await user.clear(screen.getByLabelText("Due hours"));
    await user.type(screen.getByLabelText("Due hours"), "24");
    await user.clear(screen.getByLabelText("Escalation hours"));
    await user.type(screen.getByLabelText("Escalation hours"), "36");

    await user.click(screen.getByRole("button", { name: "Save policy" }));

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1));
    expect(onSubmit).toHaveBeenCalledWith(
      expect.objectContaining({
        name: "Award approval route",
        subjectType: "rfq_award_recommendation",
        rules: [{ field: "recommendedAmount", operator: "gte", value: 10000 }],
        routeTemplate: expect.objectContaining({
          stages: [
            expect.objectContaining({
              name: "Commercial review",
              completionRule: "any",
              approvers: [expect.objectContaining({ type: "role", role: "buyer", label: "Buyer" })],
              fallbackApprovers: [
                expect.objectContaining({ type: "role", role: "admin", label: "Admin fallback" }),
              ],
            }),
          ],
        }),
        slaRules: [{ stage: "Commercial review", dueInHours: 24, escalateAfterHours: 36 }],
      }),
      expect.anything(),
    );
  }, 10000);

  it("clears stale rule fields when the subject type changes", async () => {
    const user = userEvent.setup();
    render(<ApprovalPolicyForm onSubmit={() => undefined} />, { wrapper: TestQueryProvider });

    await user.click(screen.getByRole("button", { name: /Add rule/ }));

    expect(screen.getByLabelText("Rule 1 field")).toHaveValue("amount");

    await user.selectOptions(screen.getByLabelText("Subject type"), "rfq_award_recommendation");

    await waitFor(() => {
      expect(screen.queryByLabelText("Rule 1 field")).not.toBeInTheDocument();
    });
  });
});

function TestQueryProvider({ children }: { children: ReactNode }) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}
