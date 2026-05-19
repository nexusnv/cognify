import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { RightPanelProvider } from "@/components/right-panel/right-panel-provider";
import { RightPanelRoot } from "@/components/right-panel/right-panel-root";
import { resetIdentityMockState } from "@/features/identity/mocks/identity-handlers";
import { resetRfqInvitationMockState } from "../mocks/rfq-invitation-handlers";
import { resetRfqMockState } from "../mocks/rfq-handlers";
import { resetVendorMockState } from "../mocks/vendor-handlers";
import { RfqDraftWorkspace } from "../workflows/rfq-draft-workspace";

const pushMock = vi.fn();

vi.mock("next/navigation", async (importOriginal) => {
  const actual = await importOriginal<typeof import("next/navigation")>();
  return {
    ...actual,
    useRouter: () => ({
      push: pushMock,
    }),
  };
});

function TestAppProviders({ children }: { children: React.ReactNode }) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return (
    <QueryClientProvider client={queryClient}>
      <RightPanelProvider>
        {children}
        <RightPanelRoot />
      </RightPanelProvider>
    </QueryClientProvider>
  );
}

beforeEach(() => {
  resetIdentityMockState();
  resetRfqMockState();
  resetRfqInvitationMockState();
  resetVendorMockState();
  window.localStorage.clear();
  window.localStorage.setItem("cognify.activeTenantId", "1");
  pushMock.mockReset();
});

describe("RFQ invitation workflow", () => {
  it("renders the invitation panel with MSW data", async () => {
    render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Vendor invitations" })).toBeInTheDocument();
    expect(await screen.findByText("Northwind Traders")).toBeInTheDocument();
    expect(screen.getByText("Invitation recorded")).toBeInTheDocument();
  });

  it("lets a buyer search vendors and create invitations", async () => {
    const user = userEvent.setup();

    render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    await user.click(await screen.findByRole("button", { name: "Invite vendors" }));

    const dialog = await screen.findByRole("dialog", { name: "Invite vendors to RFQ" });
    expect(dialog).toBeInTheDocument();

    const searchInput = screen.getByLabelText("Search vendors");
    await user.type(searchInput, "Atlas");

    expect(await screen.findByRole("checkbox", { name: "Atlas Workplace Supply" })).toBeInTheDocument();
    await user.click(screen.getByRole("checkbox", { name: "Atlas Workplace Supply" }));
    await user.click(screen.getByRole("button", { name: "Create invitations" }));

    await waitFor(() => {
      expect(screen.getByText("Atlas Workplace Supply")).toBeInTheDocument();
      expect(screen.getAllByText("Invitation recorded").length).toBeGreaterThan(0);
    });
  });

  it("shows a duplicate invitation error and lets the buyer recover", async () => {
    const user = userEvent.setup();

    render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    await user.click(await screen.findByRole("button", { name: "Invite vendors" }));
    await user.click(await screen.findByRole("checkbox", { name: "Northwind Traders" }));
    await user.click(screen.getByRole("button", { name: "Create invitations" }));

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Northwind Traders already has an active invitation for this RFQ.",
    );

    await user.click(screen.getByRole("checkbox", { name: "Northwind Traders" }));
    await user.click(screen.getByRole("checkbox", { name: "Atlas Workplace Supply" }));
    await user.click(screen.getByRole("button", { name: "Create invitations" }));

    await waitFor(() => {
      expect(screen.getByText("Atlas Workplace Supply")).toBeInTheDocument();
    });
  });

  it("requires a cancel reason and refreshes the invitation list after cancel", async () => {
    const user = userEvent.setup();

    render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    const invitation = await screen.findByText("Northwind Traders");
    const invitationCard = invitation.closest("[data-testid='rfq-invitation-card']");
    expect(invitationCard).toBeTruthy();

    await user.click(screen.getByRole("button", { name: "Cancel invitation" }));
    expect(await screen.findByRole("dialog", { name: "Cancel invitation" })).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Confirm cancel" }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Cancel reason is required.");

    await user.type(screen.getByLabelText("Invitation cancel reason"), "Vendor is out of scope.");
    await user.click(screen.getByRole("button", { name: "Confirm cancel" }));

    await waitFor(() => {
      expect(screen.getByText("cancelled")).toBeInTheDocument();
      expect(screen.getByText(/Cancel reason: Vendor is out of scope\./)).toBeInTheDocument();
    });
  });

  it("shows read-only invitation state after the RFQ is cancelled", async () => {
    const user = userEvent.setup();

    render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Field laptop refresh RFQ" })).toBeInTheDocument();
    await user.type(screen.getByLabelText("Cancel reason"), "This RFQ is no longer needed.");
    await user.click(screen.getByRole("button", { name: "Cancel draft" }));

    await waitFor(() => {
      expect(screen.getByText("Vendor invitations are read-only because this RFQ is cancelled.")).toBeInTheDocument();
      expect(screen.queryByRole("button", { name: "Invite vendors" })).not.toBeInTheDocument();
    });
  });

  it("shows a clear empty state in the vendor picker", async () => {
    const user = userEvent.setup();

    render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    await user.click(await screen.findByRole("button", { name: "Invite vendors" }));
    await user.type(screen.getByLabelText("Search vendors"), "does-not-exist");

    expect(await screen.findByText('No vendors match "does-not-exist".')).toBeInTheDocument();
    expect(screen.getByText("Try another search term or clear the search.")).toBeInTheDocument();
  });
});
