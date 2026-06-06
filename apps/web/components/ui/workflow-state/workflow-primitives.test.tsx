// shadcn-factory-exception: Workflow status and activity primitives need route-independent coverage for the documented workflow-state exception; primitives=Badge,Card; routes=requisitions,sourcing

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

  it("renders metadata rows when present", () => {
    render(
      <ActivityTimeline
        events={[
          {
            id: "audit-2",
            action: "requisition.updated",
            message: "Requisition updated",
            occurredAt: "2026-05-13T08:30:00.000Z",
            actor: { name: "Test User" },
            metadata: {
              amount: "MYR 3,600.00",
              approvers: ["Finance", "Procurement"],
              extra: { note: "Rechecked" },
            },
          },
        ]}
      />,
    );

    expect(screen.getByText("amount")).toBeInTheDocument();
    expect(screen.getByText("MYR 3,600.00")).toBeInTheDocument();
    expect(screen.getByText("approvers")).toBeInTheDocument();
    expect(screen.getByText("approvers").nextElementSibling).toHaveTextContent("Finance");
    expect(screen.getByText("approvers").nextElementSibling).toHaveTextContent("Procurement");
    expect(screen.getByText("extra")).toBeInTheDocument();
    expect(screen.getByText("extra").nextElementSibling).toHaveTextContent("Rechecked");
  });

  it("renders malformed dates with a safe fallback", () => {
    render(
      <ActivityTimeline
        events={[
          {
            id: "audit-3",
            action: "requisition.updated",
            message: "Requisition updated",
            occurredAt: "not-a-date",
            actor: { name: "Test User" },
          },
        ]}
      />,
    );

    expect(screen.getByText(/Unknown date/)).toBeInTheDocument();
    expect(screen.queryByText(/Invalid Date/)).not.toBeInTheDocument();
  });

  it("truncates long complex metadata values and keeps the full value in a title", () => {
    const longNote = "A".repeat(240);

    render(
      <ActivityTimeline
        events={[
          {
            id: "audit-4",
            action: "requisition.updated",
            message: "Requisition updated",
            occurredAt: "2026-05-13T08:30:00.000Z",
            actor: { name: "Test User" },
            metadata: {
              extra: { note: longNote },
            },
          },
        ]}
      />,
    );

    const extraValue = screen.getByText("extra").nextElementSibling;
    expect(extraValue).toHaveTextContent("...");
    expect(extraValue?.textContent?.length).toBeLessThan(240);
    expect(extraValue).toHaveAttribute("title", JSON.stringify({ note: longNote }));
  });
});
