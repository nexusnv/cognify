import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { RightPanelProvider } from "@/components/right-panel/right-panel-provider";
import { RightPanelRoot } from "@/components/right-panel/right-panel-root";
import { server } from "@/tests/msw/server";
import { resetIdentityMockState } from "@/features/identity/mocks/identity-handlers";
import { resetSourcingMockState } from "../mocks/sourcing-handlers";
import { SourcingIntakeDetailPage } from "../workflows/sourcing-intake-detail-page";
import { SourcingIntakeListPage } from "../workflows/sourcing-intake-list-page";

const router = {
  push: vi.fn(),
};

vi.mock("next/navigation", async (importOriginal) => {
  const actual = await importOriginal<typeof import("next/navigation")>();
  return {
    ...actual,
    useRouter: () => router,
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
  window.localStorage.clear();
  window.localStorage.setItem("cognify.activeTenantId", "1");
  router.push.mockReset();
});

describe("sourcing intake workflow", () => {
  it("renders buyer intake queue and links to detail", async () => {
    render(<SourcingIntakeListPage />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Sourcing intake" })).toBeInTheDocument();
    expect((await screen.findAllByText("Field laptop refresh")).length).toBeGreaterThan(0);
    expect(screen.getAllByRole("link", { name: /Open/ })[0]).toHaveAttribute("href", "/sourcing/intake/sourcing-1");
  });

  it("claims an unassigned review", async () => {
    const user = userEvent.setup();
    render(<SourcingIntakeDetailPage reviewId="sourcing-1" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Field laptop refresh" })).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Claim" }));

    await waitFor(() => {
      expect(screen.getByText("Priya Buyer")).toBeInTheDocument();
    });
  });

  it("saves checklist and classification", async () => {
    const user = userEvent.setup();
    render(<SourcingIntakeDetailPage reviewId="sourcing-2" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Warehouse packing supplies" })).toBeInTheDocument();
    await user.clear(screen.getByLabelText("Category"));
    await user.type(screen.getByLabelText("Category"), "IT Hardware");
    await user.click(screen.getByLabelText("Budget checked"));
    await user.click(screen.getByRole("button", { name: "Save review" }));

    await waitFor(() => {
      expect(screen.getByDisplayValue("IT Hardware")).toBeInTheDocument();
    });
  });

  it("marks review ready for RFQ without creating an RFQ", async () => {
    const user = userEvent.setup();
    render(<SourcingIntakeDetailPage reviewId="sourcing-2" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Warehouse packing supplies" })).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Record decision" }));
    await user.type(screen.getByLabelText("Decision reason"), "Competitive quotes are required for this package.");
    await user.click(screen.getByRole("button", { name: "Mark ready for RFQ" }));

    await waitFor(() => {
      expect(screen.getByText("Ready for RFQ")).toBeInTheDocument();
      expect(screen.getByRole("button", { name: "Create RFQ" })).toBeEnabled();
    });
  });

  it("shows an error when RFQ creation is forbidden", async () => {
    const user = userEvent.setup();
    server.use(
      http.post("/api/sourcing/intake-reviews/:reviewId/rfq", () =>
        HttpResponse.json({ error: { code: "forbidden", message: "No access" } }, { status: 403 }),
      ),
    );

    render(<SourcingIntakeDetailPage reviewId="sourcing-4" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("button", { name: "Create RFQ" })).toBeEnabled();
    await user.click(screen.getByRole("button", { name: "Create RFQ" }));

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "RFQ draft could not be created. Refresh and try again.",
    );
    expect(router.push).not.toHaveBeenCalled();
  });

  it("shows load error states", async () => {
    server.use(
      http.get("/api/sourcing/intake-reviews/:reviewId", () =>
        HttpResponse.json({ error: { code: "not_found", message: "Missing" } }, { status: 404 }),
      ),
    );

    render(<SourcingIntakeDetailPage reviewId="missing" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("alert")).toHaveTextContent("Unable to load sourcing intake review.");
  });

  it("recovers from stale decision conflict by refetching", async () => {
    const user = userEvent.setup();
    server.use(
      http.post("/api/sourcing/intake-reviews/:reviewId/decision", () =>
        HttpResponse.json({ error: { code: "conflict", message: "Changed" } }, { status: 409 }),
      ),
    );

    render(<SourcingIntakeDetailPage reviewId="sourcing-2" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Warehouse packing supplies" })).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Record decision" }));
    await user.type(screen.getByLabelText("Decision reason"), "Competitive quotes are required for this package.");
    await user.click(screen.getByRole("button", { name: "Mark ready for RFQ" }));

    expect(await screen.findByText("Decision could not be recorded. Refresh and try again.")).toBeInTheDocument();
  });
});
