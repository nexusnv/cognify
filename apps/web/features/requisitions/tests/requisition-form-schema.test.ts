import { describe, expect, it } from "vitest";
import { requisitionSubmitSchema } from "../schemas/requisition-form-schema";

describe("requisition submit schema", () => {
  it("requires justification, needed-by date, and one complete line item for submission", () => {
    const result = requisitionSubmitSchema.safeParse({
      title: "Office chairs",
      businessJustification: "",
      neededByDate: "",
      currency: "MYR",
      lineItems: [
        {
          name: "",
          quantity: 0,
          unit: "",
          estimatedUnitPrice: 0,
          currency: "MYR",
        },
      ],
    });

    expect(result.success).toBe(false);
    expect(result.error?.flatten().fieldErrors.businessJustification).toEqual([
      "Business justification is required before submission.",
    ]);
    expect(result.error?.flatten().fieldErrors.neededByDate).toEqual([
      "Needed-by date is required before submission.",
    ]);
  });
});
