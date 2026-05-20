import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { fireEvent, render, screen, within, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { RightPanelProvider } from "@/components/right-panel/right-panel-provider";
import { RightPanelRoot } from "@/components/right-panel/right-panel-root";
import { resetIdentityMockState } from "@/features/identity/mocks/identity-handlers";
import { server } from "@/tests/msw/server";
import { rfqDraftFixture } from "../mocks/rfq-fixtures";
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
  it("renders the invitation panel with invitation count and status summary", async () => {
    render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Vendor invitations" })).toBeInTheDocument();
    expect(await screen.findByText("1 invitation recorded")).toBeInTheDocument();
    expect(await screen.findByText("1 sent")).toBeInTheDocument();
    expect(await screen.findByText("Northwind Traders")).toBeInTheDocument();
    expect(await screen.findByText("Invitation recorded")).toBeInTheDocument();
  });

  it("lets a buyer search vendors and create invitations with buyer instructions and a response due date", async () => {
    render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Vendor invitations" })).toBeInTheDocument();
    fireEvent.click(await screen.findByRole("button", { name: "Invite vendors" }));

    const dialog = await screen.findByRole("dialog", { name: "Invite vendors to RFQ" });
    expect(dialog).toBeInTheDocument();
    expect(screen.getByLabelText("Buyer message / instructions")).toHaveValue(
      "Submit pricing, warranty, and delivery terms.",
    );
    expect(screen.getByLabelText("Response due date")).toHaveValue(
      toDateTimeLocalValue(rfqDraftFixture.responseDueAt),
    );

    fireEvent.change(within(dialog).getByLabelText("Search vendors"), { target: { value: "Atlas" } });

    expect(await screen.findByRole("checkbox", { name: "Atlas Workplace Supply" })).toBeInTheDocument();
    fireEvent.click(screen.getByRole("checkbox", { name: "Atlas Workplace Supply" }));
    fireEvent.change(within(dialog).getByLabelText("Buyer message / instructions"), {
      target: { value: "Please confirm pricing and delivery terms." },
    });
    fireEvent.change(within(dialog).getByLabelText("Response due date"), { target: { value: "2026-07-01T09:30" } });
    fireEvent.click(screen.getByRole("button", { name: "Create invitations" }));

    const invitationHeading = await screen.findByRole("heading", { name: "Atlas Workplace Supply" });
    const invitationCard = invitationHeading.closest("[data-testid='rfq-invitation-card']");
    expect(invitationCard).not.toBeNull();

    const invitationScope = within(invitationCard as HTMLElement);
    invitationScope.getByText("Invitation recorded");
    invitationScope.getByText("Please confirm pricing and delivery terms.");
    invitationScope.getByText(`Response due ${formatDateTime(new Date("2026-07-01T09:30").toISOString())}`);
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

  it("lets a buyer resend and update invitation status", async () => {
    const user = userEvent.setup();

    render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    await screen.findByText("Northwind Traders");
    await user.click(screen.getByRole("button", { name: "Resend" }));
    await user.click(screen.getByRole("button", { name: "Mark acknowledged" }));

    await waitFor(() => {
      expect(screen.getByText("acknowledged")).toBeInTheDocument();
      expect(screen.getByText("Vendor acknowledged")).toBeInTheDocument();
      expect(screen.getByText("1 acknowledged")).toBeInTheDocument();
    });
  });

  it("lets a buyer generate a manual vendor portal link without email delivery", async () => {
    const user = userEvent.setup();
    const clipboardWrite = vi.fn().mockResolvedValue(undefined);
    const originalClipboard = navigator.clipboard;

    try {
      Object.defineProperty(navigator, "clipboard", {
        configurable: true,
        value: { writeText: clipboardWrite },
      });

      render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

      await screen.findByText("Northwind Traders");
      await user.click(screen.getByRole("button", { name: "Generate portal link" }));

      expect(
        await screen.findByText("Portal link copied. Manual sharing only; email delivery is not enabled."),
      ).toBeInTheDocument();
      expect(clipboardWrite).toHaveBeenCalledWith(
        expect.stringContaining("/vendor/rfq-invitations/vendor-portal-valid-token"),
      );
    } finally {
      Object.defineProperty(navigator, "clipboard", {
        configurable: true,
        value: originalClipboard,
      });
    }
  });

  it("lets a buyer upload quotation evidence on an active invitation", async () => {
    const user = userEvent.setup();

    render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    const invitationCard = (await screen.findByText("Northwind Traders")).closest(
      "[data-testid='rfq-invitation-card']",
    );
    expect(invitationCard).toBeTruthy();

    const card = within(invitationCard as HTMLElement);
    expect(card.getByText("Quotation evidence")).toBeInTheDocument();
    expect(card.getByText("No quotation files received yet.")).toBeInTheDocument();

    const quotationFile = new File(["buyer quotation"], "buyer-received-quotation.pdf", {
      type: "application/pdf",
    });

    await user.upload(card.getByLabelText("Buyer-received quotation file"), quotationFile);
    await user.click(card.getByRole("button", { name: "Upload buyer-received quotation" }));

    await waitFor(() => {
      expect(card.getByText("Quotation received")).toBeInTheDocument();
      expect(card.getByText("buyer-received-quotation.pdf")).toBeInTheDocument();
      expect(card.getByText("1 file received")).toBeInTheDocument();
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

    fireEvent.change(screen.getByLabelText("Invitation cancel reason"), { target: { value: "Vendor is out of scope." } });
    await user.click(screen.getByRole("button", { name: "Confirm cancel" }));

    const cancelledStatus = await screen.findByText("Invitation cancelled");
    const cancelledCard = cancelledStatus.closest("[data-testid='rfq-invitation-card']");
    expect(cancelledCard).not.toBeNull();
    expect(within(cancelledCard as HTMLElement).getByText("Cancel reason: Vendor is out of scope.")).toBeInTheDocument();
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

  it("shows a read-only invitation state for non-draft RFQs", async () => {
    server.use(
      http.get("/api/rfqs/:rfqId", () =>
        HttpResponse.json({
          data: {
            ...structuredClone(rfqDraftFixture),
            status: "open",
            permissions: {
              ...rfqDraftFixture.permissions,
              canInviteVendors: true,
            },
          },
        }),
      ),
    );

    render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Field laptop refresh RFQ" })).toBeInTheDocument();
    expect(
      await screen.findByText("Vendor invitations are available only while the RFQ is a draft."),
    ).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Invite vendors" })).not.toBeInTheDocument();
  });

  it("shows a clear empty state in the vendor picker", async () => {
    const user = userEvent.setup();

    render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    await user.click(await screen.findByRole("button", { name: "Invite vendors" }));
    await user.type(screen.getByLabelText("Search vendors"), "does-not-exist");

    expect(await screen.findByText('No vendors match "does-not-exist".')).toBeInTheDocument();
    expect(screen.getByText("Try another search term or clear the search.")).toBeInTheDocument();
  });

  it("shows an empty-state copy when no active vendors are available for invitation", async () => {
    const user = userEvent.setup();

    server.use(http.get("/api/vendors", () => HttpResponse.json({ data: [] })));

    render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    await user.click(await screen.findByRole("button", { name: "Invite vendors" }));

    expect(
      await screen.findByText("No active vendors are available for invitation."),
    ).toBeInTheDocument();
    expect(screen.queryByText("Try another search term or clear the search.")).not.toBeInTheDocument();
  });
});

function formatDateTime(value: string) {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function toDateTimeLocalValue(value: string | null | undefined): string {
  if (!value) return "";

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";

  const offsetMs = date.getTimezoneOffset() * 60_000;
  const localDate = new Date(date.getTime() - offsetMs);
  return localDate.toISOString().slice(0, 16);
}
