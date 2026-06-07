import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import Link from "next/link";
import { describe, expect, it, vi } from "vitest";
import { RightPanelProvider, useRightPanel } from "./right-panel-provider";
import { RightPanelRoot } from "./right-panel-root";

vi.mock("next/navigation", () => ({
  usePathname: () => "/requisitions",
}));

function PanelHarness() {
  const rightPanel = useRightPanel();
  const replacementPanel = {
    id: "replacement",
    title: "Replacement panel",
    content: <p>Second panel content</p>,
  };

  return (
    <>
      <button
        type="button"
        onClick={() =>
          rightPanel.openPanel({
            id: "requisition-preview",
            title: "Field laptop refresh",
            description: "REQ-2026-000001",
            size: "md",
            content: (
              <>
                <p>Requester: Test User</p>
                <button type="button" onClick={() => rightPanel.openPanel(replacementPanel)}>
                  Replace panel from content
                </button>
              </>
            ),
            footer: <Link href="/requisitions/req-1">Open workspace</Link>,
          })
        }
      >
        Open panel
      </button>
      <button
        type="button"
        onClick={() => rightPanel.openPanel(replacementPanel)}
      >
        Replace panel
      </button>
      <RightPanelRoot />
    </>
  );
}

function renderPanel() {
  return render(
    <RightPanelProvider>
      <PanelHarness />
    </RightPanelProvider>,
  );
}

describe("right panel", () => {
  it("opens a labelled dialog with content and footer", async () => {
    const user = userEvent.setup();
    renderPanel();

    await user.click(screen.getByRole("button", { name: "Open panel" }));

    expect(screen.getByRole("dialog", { name: "Field laptop refresh" })).toBeInTheDocument();
    expect(screen.getByText("REQ-2026-000001")).toBeInTheDocument();
    expect(screen.getByText("Requester: Test User")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Open workspace" })).toHaveAttribute(
      "href",
      "/requisitions/req-1",
    );
  });

  it("replaces panel content without rendering stale content", async () => {
    const user = userEvent.setup();
    renderPanel();

    await user.click(screen.getByRole("button", { name: "Open panel" }));
    await user.click(screen.getByRole("button", { name: "Replace panel from content" }));

    expect(screen.getByRole("dialog", { name: "Replacement panel" })).toBeInTheDocument();
    expect(screen.getByText("Second panel content")).toBeInTheDocument();
    expect(screen.queryByText("Requester: Test User")).not.toBeInTheDocument();
  });

  it("closes by close button, Escape, and overlay", async () => {
    const user = userEvent.setup();
    renderPanel();

    await user.click(screen.getByRole("button", { name: "Open panel" }));
    await user.click(screen.getByRole("button", { name: "Close panel" }));
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Open panel" }));
    await user.keyboard("{Escape}");
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Open panel" }));
    const overlay = document.querySelector('[data-slot="sheet-overlay"]');
    expect(overlay).toBeInTheDocument();
    await user.click(overlay as Element);
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });

  it("moves focus into the panel, traps Tab, and restores focus on close", async () => {
    const user = userEvent.setup();
    renderPanel();
    const opener = screen.getByRole("button", { name: "Open panel" });

    await user.click(opener);

    expect(screen.getByRole("button", { name: "Close panel" })).toHaveFocus();

    await user.tab({ shift: true });
    expect(screen.getByRole("link", { name: "Open workspace" })).toHaveFocus();

    await user.keyboard("{Escape}");
    expect(opener).toHaveFocus();
  });
});
