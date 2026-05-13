import { CheckCircle2, CircleDot, FileClock, Send } from "lucide-react";
import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { ActivityTimeline } from "./activity-timeline";
import { StatusBadge } from "./status-badge";
import type { WorkflowStateConfig } from "./workflow-state";

type TestStatus = "draft" | "submitted";

const statusConfig = {
  draft: {
    label: "Draft",
    description: "The requester can still edit this record.",
    tone: "draft",
    icon: CircleDot,
  },
  submitted: {
    label: "Submitted",
    description: "The record has been submitted for review.",
    tone: "success",
    icon: CheckCircle2,
  },
} satisfies WorkflowStateConfig<TestStatus>;

describe("StatusBadge", () => {
  it("renders icon, label, and accessible description", () => {
    render(<StatusBadge status="draft" config={statusConfig} />);

    expect(screen.getByText("Draft")).toBeInTheDocument();
    expect(screen.getByText("The requester can still edit this record.")).toHaveClass("sr-only");
  });

  it("supports compact rendering", () => {
    render(<StatusBadge status="submitted" config={statusConfig} size="compact" />);

    expect(screen.getByText("Submitted")).toBeInTheDocument();
  });
});

describe("ActivityTimeline", () => {
  it("renders an empty state", () => {
    render(<ActivityTimeline events={[]} emptyMessage="No activity yet." />);

    expect(screen.getByText("No activity yet.")).toBeInTheDocument();
  });

  it("renders audit-shaped events with actor and time", () => {
    render(
      <ActivityTimeline
        events={[
          {
            id: "audit-1",
            action: "requisition.submitted",
            message: "Requisition submitted",
            occurredAt: "2026-05-13T08:00:00.000Z",
            actor: { id: "user-1", name: "Test User", email: "test@example.com" },
          },
        ]}
        actionIcons={{
          "requisition.submitted": Send,
          default: FileClock,
        }}
      />,
    );

    expect(screen.getByRole("list")).toBeInTheDocument();
    expect(screen.getByText("Requisition submitted")).toBeInTheDocument();
    expect(screen.getByText(/Test User/)).toBeInTheDocument();
    expect(screen.getByText(/2026/)).toBeInTheDocument();
  });
});
