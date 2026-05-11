import { describe, expect, it } from "vitest";
import { buildSubmissionChecklist, calculateEstimatedTotal } from "../utils/requisition-totals";

describe("requisition totals", () => {
  it("calculates line totals and estimated totals with decimal quantities", () => {
    const result = calculateEstimatedTotal([
      {
        quantity: 2,
        estimatedUnitPrice: 1250.5,
        currency: "MYR",
      },
      {
        quantity: 1.5,
        estimatedUnitPrice: 300,
        currency: "MYR",
      },
    ]);

    expect(result.currency).toBe("MYR");
    expect(result.lineTotals).toEqual([2501, 450]);
    expect(result.estimatedTotal).toBe(2951);
    expect(result.hasCurrencyMismatch).toBe(false);
  });

  it("flags mixed currencies for submission readiness", () => {
    const checklist = buildSubmissionChecklist({
      title: "Laptop refresh",
      businessJustification: "Replace unsupported devices for field buyers.",
      neededByDate: "2026-06-01",
      lineItems: [
        {
          name: "Laptop",
          quantity: 2,
          unit: "each",
          estimatedUnitPrice: 1200,
          currency: "MYR",
        },
        {
          name: "Dock",
          quantity: 2,
          unit: "each",
          estimatedUnitPrice: 180,
          currency: "USD",
        },
      ],
    });

    expect(checklist).toContainEqual({
      id: "currency",
      label: "Line items use one currency",
      complete: false,
    });
  });
});
