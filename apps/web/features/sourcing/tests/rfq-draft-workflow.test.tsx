import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { RightPanelProvider } from "@/components/right-panel/right-panel-provider";
import { RightPanelRoot } from "@/components/right-panel/right-panel-root";
import { resetIdentityMockState } from "@/features/identity/mocks/identity-handlers";
import { resetRfqMockState } from "../mocks/rfq-handlers";
import { resetSourcingMockState } from "../mocks/sourcing-handlers";
import { RfqDraftWorkspace } from "../workflows/rfq-draft-workspace";
import { SourcingIntakeDetailPage } from "../workflows/sourcing-intake-detail-page";

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
  resetSourcingMockState();
  resetRfqMockState();
  window.localStorage.clear();
  window.localStorage.setItem("cognify.activeTenantId", "1");
  pushMock.mockReset();
});

describe("RFQ draft workflow", () => {
  it("creates an RFQ draft from a ready intake review and navigates to it", async () => {
    const user = userEvent.setup();

    render(<SourcingIntakeDetailPage reviewId="sourcing-4" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Contract management tool renewal" })).toBeInTheDocument();
    expect(await screen.findByRole("button", { name: "Create RFQ" })).toBeEnabled();

    await user.click(screen.getByRole("button", { name: "Create RFQ" }));

    await waitFor(() => expect(pushMock).toHaveBeenCalledWith("/sourcing/rfqs/rfq-1"));
  });

  it("renders RFQ draft workspace and saves edits", async () => {
    const user = userEvent.setup();

    const { unmount } = render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Field laptop refresh RFQ" })).toBeInTheDocument();
    await user.clear(screen.getByLabelText("Title"));
    await user.type(screen.getByLabelText("Title"), "Updated laptop RFQ");
    await user.click(screen.getByRole("button", { name: "Save changes" }));

    unmount();

    render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    expect(await screen.findByDisplayValue("Updated laptop RFQ")).toBeInTheDocument();
  });

  it("cancels a draft and renders cancelled status", async () => {
    const user = userEvent.setup();

    render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Field laptop refresh RFQ" })).toBeInTheDocument();
    await user.type(screen.getByLabelText("Cancel reason"), "This sourcing package is no longer required.");
    await user.click(screen.getByRole("button", { name: "Cancel draft" }));

    await waitFor(() => {
      expect(screen.getByText("This RFQ draft is read-only.")).toBeInTheDocument();
      expect(screen.getByLabelText("Title")).toBeDisabled();
    });
  });
});
