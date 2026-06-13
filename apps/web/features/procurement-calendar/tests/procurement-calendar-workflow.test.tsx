import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { server } from "@/tests/msw/server";
import { getProcurementCalendarFixture } from "../mocks/procurement-calendar-fixtures";
import { procurementCalendarHandlers } from "../mocks/procurement-calendar-handlers";
import { ProcurementCalendarPage } from "../workflows/procurement-calendar-page";

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <ProcurementCalendarPage />
    </QueryClientProvider>,
  );
}

beforeEach(() => {
  vi.useFakeTimers({ toFake: ["Date"] });
  vi.setSystemTime(new Date("2026-06-10T00:00:00.000Z"));
  window.localStorage.clear();
  window.localStorage.setItem("cognify.activeTenantId", "tenant-1");
  server.use(...procurementCalendarHandlers);
});

afterEach(() => {
  vi.useRealTimers();
});

describe("procurement calendar workflow", () => {
  it("renders a populated calendar workspace with the heading", async () => {
    renderPage();

    expect(await screen.findByRole("heading", { name: "Calendar" })).toBeInTheDocument();
    expect(await screen.findByText("RFQ response due")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Today" })).toBeInTheDocument();
  });

  it("groups month view events by date", async () => {
    renderPage();

    const group = await screen.findByRole("region", { name: "2026-06-10" });
    expect(within(group).getByText("RFQ response due")).toBeInTheDocument();

    const secondGroup = screen.getByRole("region", { name: "2026-06-11" });
    expect(within(secondGroup).getByText("Approval due")).toBeInTheDocument();
  });

  it("sorts agenda view events chronologically", async () => {
    const fixture = getProcurementCalendarFixture();
    const unsortedEvents = [
      fixture.events[4]!,
      fixture.events[0]!,
      fixture.events[2]!,
      fixture.events[1]!,
      fixture.events[3]!,
    ];

    server.use(
      http.get("*/api/procurement-calendar/events", () =>
        HttpResponse.json({
          data: {
            ...fixture,
            events: unsortedEvents,
          },
        }),
      ),
    );

    const user = userEvent.setup();
    renderPage();

    await screen.findByText("RFQ response due");
    await user.click(screen.getByRole("button", { name: "Agenda" }));

    const items = await screen.findAllByTestId("calendar-agenda-item");
    expect(items.map((item) => item.textContent)).toEqual([
      expect.stringContaining("RFQ response due"),
      expect.stringContaining("Approval due"),
      expect.stringContaining("Requisition needed by date"),
      expect.stringContaining("PO handoff"),
      expect.stringContaining("Quotation validity expiring"),
    ]);
  });

  it("updates visible events when the source filter changes", async () => {
    const user = userEvent.setup();
    renderPage();

    expect(await screen.findByRole("button", { name: /RFQ response due/i })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /Approval due/i })).toBeInTheDocument();

    await user.click(screen.getByRole("checkbox", { name: "Approval due" }));

    await waitFor(() => {
      expect(screen.queryByRole("button", { name: /RFQ response due/i })).not.toBeInTheDocument();
    });

    expect(screen.getByRole("button", { name: /Approval due/i })).toBeInTheDocument();
  });

  it("shows a filtered empty state when search removes all events", async () => {
    const user = userEvent.setup();
    renderPage();

    const search = await screen.findByLabelText("Search");
    await user.clear(search);
    await user.type(search, "no matching procurement item");

    expect(await screen.findByText("No events match the current filters.")).toBeInTheDocument();
  });

  it("shows source metadata and a source link in the event detail", async () => {
    const user = userEvent.setup();
    renderPage();

    await user.click(await screen.findByRole("button", { name: /RFQ response due/i }));

    const detail = screen.getByLabelText("Event detail");

    expect(await within(detail).findByRole("heading", { name: "RFQ response due" })).toBeInTheDocument();
    expect(within(detail).getByText("rfq-ready")).toBeInTheDocument();
    expect(within(detail).getByRole("link", { name: "Open source" })).toHaveAttribute("href", "/app/rfqs/rfq-ready");
    expect(within(detail).getAllByText("RFQ deadline").length).toBeGreaterThan(0);
  });

  it("shows an API error state", async () => {
    server.use(
      http.get("*/api/procurement-calendar/events", () =>
        HttpResponse.json(
          {
            error: {
              code: "calendar_unavailable",
              message: "Calendar unavailable",
            },
          },
          { status: 500 },
        ),
      ),
    );

    renderPage();

    expect(await screen.findByText("Unable to load calendar events.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Retry" })).toBeInTheDocument();
  });
});
