import type { ApiClientError } from "./client";
import type { ApiError, ApiErrorErrorCode } from "./generated/schemas";

export function isApiClientError(value: unknown): value is ApiClientError<ApiError> {
  return (
    typeof value === "object" &&
    value !== null &&
    "status" in value &&
    "data" in value &&
    typeof (value as { status?: unknown }).status === "number"
  );
}

export function getApiErrorCode(value: unknown): ApiErrorErrorCode | null {
  if (!isApiClientError(value)) {
    return null;
  }

  return value.data?.error?.code ?? null;
}

export function getApiErrorMessage(value: unknown): string {
  if (!isApiClientError(value)) {
    return "Something went wrong.";
  }

  return value.data?.error?.message ?? "Something went wrong.";
}

export function getApiValidationErrors(value: unknown): Record<string, string[]> {
  if (!isApiClientError(value)) {
    return {};
  }

  const code = value.data?.error?.code;
  if (code !== "validation_failed") {
    return {};
  }

  const details = value.data?.error?.details;

  if (!details || typeof details !== "object" || !("fields" in details)) {
    return {};
  }

  const fields = (details as { fields?: unknown }).fields;

  if (!fields || typeof fields !== "object" || Array.isArray(fields)) {
    return {};
  }

  return Object.fromEntries(
    Object.entries(fields).filter((entry): entry is [string, string[]] => {
      const [, messages] = entry;

      return Array.isArray(messages) && messages.every((message) => typeof message === "string");
    }),
  );
}
