import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { ConfirmActionDialog } from "./confirm-action-dialog";
import { EmptyState } from "./empty-state";
import { PageHeader } from "./page-header";

describe("shadcn app composites", () => {
  it("renders a page header with title, description, and actions", () => {
    render(
      <PageHeader
        eyebrow="Workspace"
        title="Requisitions"
        description="Review intake and submission status."
        actions={<button type="button">New requisition</button>}
      />,
    );

    expect(screen.getByRole("heading", { name: "Requisitions" })).toBeInTheDocument();
    expect(screen.getByText("Review intake and submission status.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "New requisition" })).toBeInTheDocument();
  });

  it("renders a confirm action dialog with accessible cancel and confirm actions", async () => {
    const onConfirm = vi.fn();
    const user = userEvent.setup();

    render(
      <ConfirmActionDialog
        triggerLabel="Cancel draft"
        title="Cancel draft?"
        description="This keeps the audit trail and stops further editing."
        confirmLabel="Cancel draft"
        onConfirm={onConfirm}
      />,
    );

    await user.click(screen.getByRole("button", { name: "Cancel draft" }));
    expect(screen.getByRole("alertdialog")).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Cancel draft" }));
    expect(onConfirm).toHaveBeenCalledTimes(1);
  });

  it("renders empty state copy and optional action", () => {
    render(
      <EmptyState
        title="No records"
        description="Create the first record."
        action={<button type="button">Create</button>}
      />,
    );

    expect(screen.getByText("No records")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Create" })).toBeInTheDocument();
  });
});
