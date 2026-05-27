import {
  ProcurementCalendarEventSourceType,
  ProcurementCalendarSourceType,
} from "@cognify/api-client/schemas";
import type {
  ProcurementCalendarAvailableSource,
  ProcurementCalendarEvent,
  ProcurementCalendarEventCollection,
  ProcurementCalendarEventStatus,
} from "@cognify/api-client/schemas";

type CalendarFixtureState = {
  events: ProcurementCalendarEventCollection;
};

const state: CalendarFixtureState = {
  events: buildCalendarCollection(),
};

function buildAvailableSource(
  sourceType: ProcurementCalendarSourceType,
  label: string,
  available: boolean,
  reason: string | null = null,
): ProcurementCalendarAvailableSource {
  return { sourceType, label, available, reason: available ? undefined : reason };
}

function buildEvent(overrides: Partial<ProcurementCalendarEvent>): ProcurementCalendarEvent {
  return {
    id: "calendar-event-1",
    sourceType: ProcurementCalendarEventSourceType.rfqDeadline,
    sourceId: "rfq-1",
    sourceLabel: "RFQ-2026-000041",
    title: "RFQ response due",
    description: "Responses are due for the laptop refresh program.",
    startsAt: "2026-06-10T09:00:00.000Z",
    endsAt: "2026-06-10T10:00:00.000Z",
    allDay: false,
    status: "scheduled",
    priority: "high",
    record: {
      type: "rfq",
      id: "rfq-1",
      label: "RFQ-2026-000041",
      href: "/app/rfqs/rfq-1",
    },
    context: {
      label: "Procurement",
      link: "/app/procurement-calendar",
    },
    ...overrides,
  };
}

function buildCalendarCollection(): ProcurementCalendarEventCollection {
  const events = [
    buildEvent({
      id: "calendar-event-rfq-deadline",
      sourceType: ProcurementCalendarEventSourceType.rfqDeadline,
      sourceId: "rfq-ready",
      sourceLabel: "RFQ-2026-000041",
      title: "RFQ response due",
      description: "Responses are due for the laptop refresh program.",
      startsAt: "2026-06-10T09:00:00.000Z",
      endsAt: "2026-06-10T10:00:00.000Z",
      status: "scheduled",
      priority: "high",
      record: { type: "rfq", id: "rfq-ready", label: "RFQ-2026-000041", href: "/app/rfqs/rfq-ready" },
    }),
    buildEvent({
      id: "calendar-event-approval-due",
      sourceType: ProcurementCalendarEventSourceType.approvalDue,
      sourceId: "approval-task-1",
      sourceLabel: "Approval task #1",
      title: "Approval due",
      description: "Purchasing approval is due today.",
      startsAt: "2026-06-11T09:00:00.000Z",
      endsAt: "2026-06-11T10:00:00.000Z",
      status: "dueSoon",
      priority: "medium",
      record: { type: "approval-task", id: "approval-task-1", label: "Approval task #1", href: "/app/approvals/approval-task-1" },
    }),
    buildEvent({
      id: "calendar-event-requisition-needed-by",
      sourceType: ProcurementCalendarEventSourceType.requisitionNeededBy,
      sourceId: "requisition-1",
      sourceLabel: "REQ-2026-001",
      title: "Requisition needed by date",
      description: "Needed-by date is approaching for the sourcing intake.",
      startsAt: "2026-06-12T09:00:00.000Z",
      endsAt: "2026-06-12T10:00:00.000Z",
      status: "scheduled",
      priority: "medium",
      record: { type: "requisition", id: "requisition-1", label: "REQ-2026-001", href: "/app/requisitions/requisition-1" },
    }),
    buildEvent({
      id: "calendar-event-po-handoff",
      sourceType: ProcurementCalendarEventSourceType.poHandoff,
      sourceId: "po-1",
      sourceLabel: "PO-2026-001",
      title: "PO handoff",
      description: "Purchase order is ready for handoff.",
      startsAt: "2026-06-13T09:00:00.000Z",
      endsAt: "2026-06-13T10:00:00.000Z",
      status: "completed",
      priority: "low",
      record: { type: "purchase-order", id: "po-1", label: "PO-2026-001", href: "/app/purchase-orders/po-1" },
    }),
    buildEvent({
      id: "calendar-event-quotation-validity",
      sourceType: ProcurementCalendarEventSourceType.quotationValidity,
      sourceId: "quotation-1",
      sourceLabel: "QT-2026-041",
      title: "Quotation validity expiring",
      description: "Vendor quotation validity ends soon.",
      startsAt: "2026-06-14T09:00:00.000Z",
      endsAt: "2026-06-14T10:00:00.000Z",
      status: "dueSoon",
      priority: "high",
      record: { type: "quotation", id: "quotation-1", label: "QT-2026-041", href: "/app/quotations/quotation-1" },
    }),
  ];

  return {
    range: {
      from: "2026-06-01",
      to: "2026-06-30",
      view: "month",
    },
    summary: {
      total: events.length,
      byStatus: {
        overdue: 0,
        dueSoon: 2,
        scheduled: 2,
        completed: 1,
        informational: 0,
      },
      bySourceType: {
        rfqDeadline: 1,
        approvalDue: 1,
        requisitionNeededBy: 1,
        poHandoff: 1,
        quotationValidity: 1,
      },
    },
    availableSources: [
      buildAvailableSource(ProcurementCalendarSourceType.rfqDeadline, "RFQ deadline", true),
      buildAvailableSource(ProcurementCalendarSourceType.approvalDue, "Approval due", true),
      buildAvailableSource(ProcurementCalendarSourceType.requisitionNeededBy, "Requisition needed by", true),
      buildAvailableSource(ProcurementCalendarSourceType.poHandoff, "PO handoff", true),
      buildAvailableSource(ProcurementCalendarSourceType.quotationValidity, "Quotation validity", true),
      buildAvailableSource(ProcurementCalendarSourceType.vendorDocumentExpiry, "Vendor document expiry", false, "No vendor document expiry events are available for this tenant."),
      buildAvailableSource(ProcurementCalendarSourceType.contractRenewal, "Contract renewal", false, "No contract renewal events are available for this tenant."),
    ],
    events,
  };
}

function matchesRange(event: ProcurementCalendarEvent, from: string, to: string) {
  return event.startsAt >= `${from}T00:00:00.000Z` && event.startsAt <= `${to}T23:59:59.999Z`;
}

function matchesQuery(event: ProcurementCalendarEvent, q: string | null) {
  if (!q) return true;
  const needle = q.toLowerCase();
  return [event.title, event.sourceLabel, event.description ?? ""].some((value) => value.toLowerCase().includes(needle));
}

function matchesFilters(
  event: ProcurementCalendarEvent,
  sourceTypes: ProcurementCalendarSourceType[],
  statuses: ProcurementCalendarEventStatus[],
  q: string | null,
  from: string,
  to: string,
) {
  const sourceTypeMatches = sourceTypes.length === 0 || sourceTypes.includes(event.sourceType);
  const statusMatches = statuses.length === 0 || statuses.includes(event.status);
  return sourceTypeMatches && statusMatches && matchesQuery(event, q) && matchesRange(event, from, to);
}

export function getProcurementCalendarFixture() {
  return state.events;
}

export function getFilteredProcurementCalendarFixture(params: {
  from: string;
  to: string;
  sourceTypes: ProcurementCalendarSourceType[];
  statuses: ProcurementCalendarEventStatus[];
  q: string | null;
}) {
  const events = state.events.events.filter((event) =>
    matchesFilters(event, params.sourceTypes, params.statuses, params.q, params.from, params.to),
  );

  return {
    ...state.events,
    range: {
      ...state.events.range,
      from: params.from,
      to: params.to,
    },
    summary: {
      ...state.events.summary,
      total: events.length,
      byStatus: {
        overdue: events.filter((event) => event.status === "overdue").length,
        dueSoon: events.filter((event) => event.status === "dueSoon").length,
        scheduled: events.filter((event) => event.status === "scheduled").length,
        completed: events.filter((event) => event.status === "completed").length,
        informational: events.filter((event) => event.status === "informational").length,
      },
      bySourceType: {
        rfqDeadline: events.filter((event) => event.sourceType === "rfqDeadline").length,
        approvalDue: events.filter((event) => event.sourceType === "approvalDue").length,
        requisitionNeededBy: events.filter((event) => event.sourceType === "requisitionNeededBy").length,
        poHandoff: events.filter((event) => event.sourceType === "poHandoff").length,
        quotationValidity: events.filter((event) => event.sourceType === "quotationValidity").length,
      },
    },
    events,
  };
}
