import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { ReactNode } from "react";
import { describe, expect, it, vi } from "vitest";
import { ApprovalPolicyForm } from "../forms/approval-policy-form";
import { defaultApprovalPolicyValues } from "../schemas/approval-policy-schema";

if (typeof window !== "undefined" && !Element.prototype.hasPointerCapture) {
  Element.prototype.hasPointerCapture = () => false;
}

if (typeof window !== "undefined" && !Element.prototype.setPointerCapture) {
  Element.prototype.setPointerCapture = () => undefined;
}

if (typeof window !== "undefined" && !Element.prototype.releasePointerCapture) {
  Element.prototype.releasePointerCapture = () => undefined;
}

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
    render(
      <ApprovalPolicyForm
        defaultValues={{
          ...defaultApprovalPolicyValues,
          subjectType: "rfq_award_recommendation",
          rules: [{ field: "recommendedAmount", operator: "gte", value: 0 }],
          routeTemplate: {
            stages: [
              {
                name: "Commercial review",
                completionRule: "any",
                approvers: [{ type: "role", role: "buyer", label: "Buyer" }],
                fallbackApprovers: [{ type: "role", role: "admin", label: "Admin fallback" }],
              },
            ],
          },
          slaRules: [{ stage: "Commercial review", dueInHours: 24, escalateAfterHours: 36 }],
        }}
        onSubmit={onSubmit}
      />,
      { wrapper: TestQueryProvider },
    );

    await user.type(screen.getByLabelText("Policy name"), "Award approval route");
    await user.clear(screen.getByLabelText("Rule 1 value"));
    await user.type(screen.getByLabelText("Rule 1 value"), "10000");

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

    expect(screen.getByRole("combobox", { name: "Rule 1 field" })).toHaveTextContent("Amount");

    await selectOption(user, "Subject type", "RFQ award recommendation");

    await waitFor(() => {
      expect(screen.queryByLabelText("Rule 1 field")).not.toBeInTheDocument();
    });
  });

  it("shows approver validation errors inline", async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<ApprovalPolicyForm onSubmit={onSubmit} />, { wrapper: TestQueryProvider });

    await user.clear(screen.getByLabelText("Approver role"));
    await user.click(screen.getByRole("button", { name: "Save policy" }));

    expect(await screen.findByText("Role is required for role approvers")).toBeInTheDocument();
    expect(onSubmit).not.toHaveBeenCalled();
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

async function selectOption(
  user: ReturnType<typeof userEvent.setup>,
  triggerLabel: string,
  optionName: string,
) {
  await user.click(screen.getByRole("combobox", { name: triggerLabel }));
  await user.click(await screen.findByRole("option", { name: optionName }));
}
