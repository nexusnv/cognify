import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { ApprovalPolicyPreview } from "../components/approval-policy-preview";
import { defaultApprovalPolicyValues } from "../schemas/approval-policy-schema";

describe("ApprovalPolicyPreview", () => {
  it("renders policy route stages without creating approval tasks", () => {
    render(<ApprovalPolicyPreview values={defaultApprovalPolicyValues} />);

    expect(screen.getByRole("heading", { name: "Policy route preview" })).toBeInTheDocument();
    expect(screen.getByText("Manager review")).toBeInTheDocument();
    expect(screen.getByText("Approver")).toBeInTheDocument();
    expect(screen.getByText("This authoring preview does not create approval tasks.")).toBeInTheDocument();
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
    );

    expect(screen.getByText("No approval stages configured.")).toBeInTheDocument();
  });
});
