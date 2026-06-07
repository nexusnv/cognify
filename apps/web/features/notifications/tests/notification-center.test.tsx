import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { NotificationHost } from "@/components/shell/notification-host";
import { resetNotificationMockState } from "../mocks/notification-handlers";

const push = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({
    push,
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
    push.mockReset();
    window.localStorage.setItem("cognify.activeTenantId", "tenant-1");
  });

  it("renders unread notifications from the hook-backed center", async () => {
    renderWithQuery(<NotificationHost />);
    fireEvent.click(await screen.findByRole("button", { name: "Open notifications, 2 unread" }));

    expect(await screen.findByText("Requisition submitted")).toBeInTheDocument();
    expect(screen.getByText("Evidence uploaded")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Mark all read" })).toBeEnabled();
  }, 15000);

  it("marks all notifications read through query invalidation", async () => {
    renderWithQuery(<NotificationHost />);
    fireEvent.click(await screen.findByRole("button", { name: "Open notifications, 2 unread" }));

    fireEvent.click(await screen.findByRole("button", { name: "Mark all read" }));

    expect(await screen.findByText("No notifications for this view.")).toBeInTheDocument();
  });

  it("shell bell renders unread count and accessible label", async () => {
    renderWithQuery(<NotificationHost />);

    expect(await screen.findByRole("button", { name: "Open notifications, 2 unread" })).toBeEnabled();
    expect(screen.getByText("2")).toBeInTheDocument();
  });

  it("marks a linked notification read before navigation", async () => {
    renderWithQuery(<NotificationHost />);
    fireEvent.click(await screen.findByRole("button", { name: "Open notifications, 2 unread" }));

    fireEvent.click(await screen.findByText("Requisition submitted"));

    await waitFor(() => {
      expect(push).toHaveBeenCalledWith("/requisitions/42");
    });
  });
});
