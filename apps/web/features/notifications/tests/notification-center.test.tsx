import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { resetNotificationMockState } from "../mocks/notification-handlers";

vi.mock("next/navigation", () => ({
  useRouter: () => ({
    push: vi.fn(),
  }),
}));

function renderWithQuery(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

describe("notification center", () => {
  beforeEach(() => {
    resetNotificationMockState();
    window.localStorage.setItem("cognify.activeTenantId", "tenant-1");
  });

  it("renders unread notifications from the hook-backed center", async () => {
    const { NotificationCenter } = await import("../components/notification-center");

    renderWithQuery(<NotificationCenter open onOpenChange={() => undefined} />);

    expect(await screen.findByText("Requisition submitted")).toBeInTheDocument();
    expect(screen.getByText("Evidence uploaded")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Mark all read" })).toBeEnabled();
  });

  it("marks all notifications read through query invalidation", async () => {
    const user = userEvent.setup();
    const { NotificationCenter } = await import("../components/notification-center");

    renderWithQuery(<NotificationCenter open onOpenChange={() => undefined} />);

    await user.click(await screen.findByRole("button", { name: "Mark all read" }));

    expect(await screen.findByText("No notifications for this view.")).toBeInTheDocument();
  });
});
