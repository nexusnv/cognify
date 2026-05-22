import type { QuotationNormalization, QuotationNormalizationSummary } from "@cognify/api-client/schemas";

export type QueueRowExtras = {
  updatedAt?: string | null;
  lastJobError?: string | null;
};

export function getUpdatedAt(record: QueueRowExtras) {
  return record.updatedAt ?? null;
}

export function getLastJobError(record: QueueRowExtras) {
  return record.lastJobError ?? null;
}

export function formatDistanceToNowLabel(value: string) {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

export function isApprovedNormalization(normalization: QuotationNormalization) {
  return normalization.status === "approved" || normalization.status === "approved_with_warnings";
}

export function withQueueExtras(row: QuotationNormalizationSummary) {
  return row as QuotationNormalizationSummary & QueueRowExtras;
}
