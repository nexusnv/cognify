import { HttpResponse, http } from "msw";
import type { ProcurementCalendarEventStatus, ProcurementCalendarSourceType } from "@cognify/api-client/schemas";
import { getFilteredProcurementCalendarFixture } from "./procurement-calendar-fixtures";

export const procurementCalendarHandlers = [
  http.get("/api/procurement-calendar/events", ({ request }) => {
    const url = new URL(request.url);
    const from = url.searchParams.get("from");
    const to = url.searchParams.get("to");

    if (!from || !to) {
      return HttpResponse.json(
        { error: { code: "validation_failed", message: "The from and to date range is required." } },
        { status: 422 },
      );
    }

    const sourceTypes = url.searchParams.getAll("sourceTypes[]") as ProcurementCalendarSourceType[];
    const statuses = url.searchParams.getAll("statuses[]") as ProcurementCalendarEventStatus[];
    const q = url.searchParams.get("q");

    return HttpResponse.json({
      data: getFilteredProcurementCalendarFixture({
        from,
        to,
        sourceTypes,
        statuses,
        q,
      }),
    });
  }),
];
