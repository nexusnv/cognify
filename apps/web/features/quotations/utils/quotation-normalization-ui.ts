import { getApiErrorCode, getApiErrorMessage } from "@cognify/api-client";
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
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "Unknown";
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

export function isApprovedNormalization(normalization: QuotationNormalization) {
  return normalization.status === "approved" || normalization.status === "approved_with_warnings";
}

export function withQueueExtras(row: QuotationNormalizationSummary) {
  return row as QuotationNormalizationSummary & QueueRowExtras;
}

export function getQuotationNormalizationErrorMessage(error: unknown) {
  const nestedCode =
    typeof error === "object" &&
    error !== null &&
    "error" in error &&
    typeof error.error === "object" &&
    error.error !== null &&
    "code" in error.error
      ? String(error.error.code)
      : null;
  const nestedMessage =
    typeof error === "object" &&
    error !== null &&
    "error" in error &&
    typeof error.error === "object" &&
    error.error !== null &&
    "message" in error.error
      ? String(error.error.message)
      : null;
  const code = nestedCode ?? getApiErrorCode(error);

  return code === "forbidden"
    ? (nestedMessage ?? "You do not have access to quotation normalizations.")
    : (nestedMessage ?? getApiErrorMessage(error));
}

export const getQuotationNormalizationQueueErrorMessage = getQuotationNormalizationErrorMessage;
