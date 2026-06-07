import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { useState } from "react";
import { describe, expect, it } from "vitest";
import { ProjectActionDialog } from "./project-action-dialog";

describe("ProjectActionDialog", () => {
  it("stays open while a pending action is in flight", async () => {
    const user = userEvent.setup();

    function TestDialog() {
      const [isPending, setIsPending] = useState(false);

      return (
        <ProjectActionDialog
          action="cancel"
          title="Cancel project?"
          description="Explain why this project should stop."
          confirmLabel="Confirm cancellation"
          triggerLabel="Cancel"
          triggerVariant="destructive"
          isPending={isPending}
          onSubmit={async () => {
            setIsPending(true);
            await new Promise(() => undefined);
          }}
        />
      );
    }

    render(
      <TestDialog />,
    );

    await user.click(screen.getByRole("button", { name: "Cancel" }));
    expect(await screen.findByRole("alertdialog", { name: "Cancel project?" })).toBeInTheDocument();
    await user.type(screen.getByLabelText("Reason"), "Budget withdrawn");
    await user.click(screen.getByRole("button", { name: "Confirm cancellation" }));

    await user.keyboard("{Escape}");

    expect(screen.getByRole("alertdialog", { name: "Cancel project?" })).toBeInTheDocument();
  });
});
