import type { RequisitionFormValues, RequisitionLineItem } from "../types/requisition-view-model";

export type SubmissionChecklistItem = {
  id: string;
  label: string;
  complete: boolean;
};

export function calculateEstimatedTotal(
  lineItems: Pick<RequisitionLineItem, "quantity" | "estimatedUnitPrice" | "currency">[],
) {
  const currencies = lineItems.map((item) => item.currency).filter(Boolean);
  const currency = currencies[0] ?? "MYR";
  const lineTotals = lineItems.map((item) =>
    roundMoney((Number(item.quantity) || 0) * (Number(item.estimatedUnitPrice) || 0)),
  );

  return {
    currency,
    lineTotals,
    estimatedTotal: roundMoney(lineTotals.reduce((sum, lineTotal) => sum + lineTotal, 0)),
    hasCurrencyMismatch: new Set(currencies).size > 1,
  };
}

export function buildSubmissionChecklist(values: Partial<RequisitionFormValues>) {
  const lineItems = values.lineItems ?? [];
  const totals = calculateEstimatedTotal(lineItems);

  return [
    {
      id: "summary",
      label: "Request summary is complete",
      complete: Boolean(
        values.title?.trim() && values.businessJustification?.trim() && values.neededByDate,
      ),
    },
    {
      id: "line-items",
      label: "At least one complete line item",
      complete:
        lineItems.length > 0 &&
        lineItems.every(
          (item) =>
            Boolean(item.name?.trim()) &&
            Boolean(item.unit?.trim()) &&
            Number(item.quantity) > 0 &&
            Number(item.estimatedUnitPrice) > 0 &&
            Boolean(item.currency?.trim()),
        ),
    },
    {
      id: "currency",
      label: "Line items use one currency",
      complete: !totals.hasCurrencyMismatch,
    },
  ] satisfies SubmissionChecklistItem[];
}

export function formatMoney(amount: number, currency: string, locale = "en-MY") {
  return new Intl.NumberFormat(locale, {
    style: "currency",
    currency,
    maximumFractionDigits: 2,
  }).format(amount || 0);
}

function roundMoney(value: number) {
  return Math.round((value + Number.EPSILON) * 100) / 100;
}
