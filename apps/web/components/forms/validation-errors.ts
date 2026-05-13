import type { FormSummaryError } from "./form-error-summary";

type FieldErrors = Record<string, string[] | undefined>;

export function flattenZodFieldErrors(fieldErrors: FieldErrors): FormSummaryError[] {
  return Object.entries(fieldErrors).flatMap(([field, messages]) =>
    normalizeMessages(messages).map((message) => ({ field, message })),
  );
}

export function normalizeValidationErrors(error: unknown): FormSummaryError[] {
  if (!error || typeof error !== "object") return [];

  const maybeError = error as {
    errors?: FieldErrors;
    details?: {
      fields?: FieldErrors;
    };
    response?: {
      data?: {
        error?: {
          details?: {
            fields?: FieldErrors;
          };
        };
      };
    };
  };

  const fields =
    maybeError.details?.fields ??
    maybeError.errors ??
    maybeError.response?.data?.error?.details?.fields ??
    {};

  return flattenZodFieldErrors(fields);
}

export function focusFirstInvalidField(root: ParentNode = document) {
  const firstInvalid = root.querySelector<HTMLElement>("[aria-invalid='true']");
  firstInvalid?.focus();
}

function normalizeMessages(messages: unknown): string[] {
  if (Array.isArray(messages)) {
    return messages.filter((message): message is string => typeof message === "string");
  }

  if (typeof messages === "string") return [messages];

  return [];
}

export function withFieldIds(
  errors: FormSummaryError[],
  fieldIds: Record<string, string>,
): FormSummaryError[] {
  return errors.map((error) => ({
    ...error,
    fieldId: error.field ? fieldIds[error.field] : undefined,
  }));
}
