import { http, HttpResponse } from "msw";
import { beforeEach, describe, expect, it } from "vitest";
import { storeActiveTenantId } from "@/features/identity/api/identity-api";
import { server } from "@/tests/msw/server";
import { fetchProcurementCalendarEvents } from "../api/procurement-calendar-api";
import { procurementCalendarHandlers } from "../mocks/procurement-calendar-handlers";

describe("procurement calendar api", () => {
  beforeEach(() => {
    window.localStorage.clear();
    server.use(...procurementCalendarHandlers);
  });

  it("sends the active tenant header and loads rfq deadline calendar events", async () => {
    storeActiveTenantId("tenant-1");
    let tenantHeader: string | null = null;

    server.use(
      http.get("*/api/procurement-calendar/events", ({ request }) => {
        tenantHeader = request.headers.get("X-Tenant-Id");
        return HttpResponse.json({
          data: {
            range: { from: "2026-06-01", to: "2026-06-30", view: "month" },
            summary: {
              total: 1,
              byStatus: { overdue: 0, dueSoon: 0, scheduled: 1, completed: 0, informational: 0 },
              bySourceType: {
                rfqDeadline: 1,
                approvalDue: 0,
                requisitionNeededBy: 0,
                poHandoff: 0,
                quotationValidity: 0,
              },
            },
            availableSources: [],
            events: [
              {
                id: "calendar-event-rfq-deadline",
                sourceType: "rfqDeadline",
                sourceId: "rfq-ready",
                sourceLabel: "RFQ-2026-000041",
                title: "RFQ response due",
                description: "Responses are due for the laptop refresh program.",
                startsAt: "2026-06-10T09:00:00.000Z",
                endsAt: "2026-06-10T10:00:00.000Z",
                allDay: false,
                status: "scheduled",
                priority: "high",
                record: { type: "rfq", id: "rfq-ready", label: "RFQ-2026-000041", href: "/app/rfqs/rfq-ready" },
                context: { label: "Procurement", link: "/app/procurement-calendar" },
              },
            ],
          },
        });
      }),
    );

    const calendar = await fetchProcurementCalendarEvents({
      from: "2026-06-01",
      to: "2026-06-30",
      view: "month",
      "sourceTypes[]": ["rfqDeadline"],
    });

    expect(tenantHeader).toBe("tenant-1");
    expect(calendar.range).toEqual({
      from: "2026-06-01",
      to: "2026-06-30",
      view: "month",
    });
    expect(calendar.events).toHaveLength(1);
    expect(calendar.events[0]).toMatchObject({
      sourceType: "rfqDeadline",
      sourceId: "rfq-ready",
      sourceLabel: "RFQ-2026-000041",
      title: "RFQ response due",
      status: "scheduled",
    });
  });
});
