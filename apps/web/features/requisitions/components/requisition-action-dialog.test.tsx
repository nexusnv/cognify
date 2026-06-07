import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { useState } from "react";
import { describe, expect, it } from "vitest";
import { RequisitionActionDialog } from "./requisition-action-dialog";

describe("RequisitionActionDialog", () => {
  it("stays open while a pending action is in flight", async () => {
    const user = userEvent.setup();

    function TestDialog() {
      const [isPending, setIsPending] = useState(false);

      return (
        <RequisitionActionDialog
          action="withdraw"
          title="Withdraw requisition?"
          description="Explain why this requisition should be withdrawn."
          confirmLabel="Confirm withdrawal"
          triggerLabel="Withdraw"
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

    await user.click(screen.getByRole("button", { name: "Withdraw" }));
    expect(
      await screen.findByRole("alertdialog", { name: "Withdraw requisition?" }),
    ).toBeInTheDocument();
    await user.type(screen.getByLabelText("Reason"), "No longer required");
    await user.click(screen.getByRole("button", { name: "Confirm withdrawal" }));

    await user.keyboard("{Escape}");

    expect(screen.getByRole("alertdialog", { name: "Withdraw requisition?" })).toBeInTheDocument();
  });
});
