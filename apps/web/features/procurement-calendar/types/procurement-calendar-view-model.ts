import type {
  ListProcurementCalendarEventsView,
  ProcurementCalendarAvailableSource,
  ProcurementCalendarEvent,
  ProcurementCalendarEventSourceType,
  ProcurementCalendarEventStatus,
} from "@cognify/api-client/schemas";

export type ProcurementCalendarView = ListProcurementCalendarEventsView;

export type ProcurementCalendarEventViewModel = ProcurementCalendarEvent & {
  dateKey: string;
  sourceTypeLabel: string;
  statusLabel: string;
  timeLabel: string;
};

export const procurementCalendarViewOptions: Array<{
  value: ProcurementCalendarView;
  label: string;
}> = [
  { value: "month", label: "Month" },
  { value: "week", label: "Week" },
  { value: "agenda", label: "Agenda" },
];

export const procurementCalendarStatusOptions: Array<{
  value: ProcurementCalendarEventStatus;
  label: string;
}> = [
  { value: "overdue", label: "Overdue" },
  { value: "dueSoon", label: "Due soon" },
  { value: "scheduled", label: "Scheduled" },
  { value: "completed", label: "Completed" },
  { value: "informational", label: "Informational" },
];

const fallbackSourceLabels: Record<ProcurementCalendarEventSourceType, string> = {
  rfqDeadline: "RFQ deadline",
  approvalDue: "Approval due",
  requisitionNeededBy: "Requisition needed by",
  poHandoff: "PO handoff",
  quotationValidity: "Quotation validity",
  vendorDocumentExpiry: "Vendor document expiry",
  contractRenewal: "Contract renewal",
};

const statusLabels: Record<ProcurementCalendarEventStatus, string> = {
  overdue: "Overdue",
  dueSoon: "Due soon",
  scheduled: "Scheduled",
  completed: "Completed",
  informational: "Informational",
};

export function getProcurementCalendarSourceLabel(
  sourceType: ProcurementCalendarEventSourceType,
  availableSources: ProcurementCalendarAvailableSource[],
) {
  return (
    availableSources.find((source) => source.sourceType === sourceType)?.label ??
    fallbackSourceLabels[sourceType]
  );
}

export function getProcurementCalendarStatusLabel(status: ProcurementCalendarEventStatus) {
  return statusLabels[status];
}
