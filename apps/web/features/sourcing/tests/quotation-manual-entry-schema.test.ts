import { describe, expect, it } from "vitest";
import {
  formValuesFromQuotation,
  quotationManualEntrySchema,
} from "../schemas/quotation-manual-entry-schema";

describe("quotation manual entry schema", () => {
  it("does not default a new quotation currency", () => {
    expect(formValuesFromQuotation(null).currency).toBe("");
  });

  it("rejects whitespace-only line item and currency fields", () => {
    const result = quotationManualEntrySchema.safeParse({
      quotationReference: "",
      quotedAt: "",
      validUntil: "",
      currency: "   ",
      subtotalAmount: "",
      taxAmount: "",
      freightAmount: "",
      discountAmount: "",
      totalAmount: "",
      paymentTerms: "",
      deliveryTerms: "",
      leadTimeDays: null,
      warrantyTerms: "",
      exclusions: "",
      complianceNotes: "",
      buyerNotes: "",
      vendorNotes: "",
      lineItems: [
        {
          rfqLineItemId: null,
          description: "   ",
          quantity: "   ",
          unit: null,
          unitPrice: null,
          subtotalAmount: null,
          taxAmount: null,
          totalAmount: null,
          leadTimeDays: null,
          manufacturer: null,
          modelNumber: null,
          alternateOffered: false,
          complianceStatus: null,
          notes: null,
        },
      ],
    });

    expect(result.success).toBe(false);
    expect(result.error?.issues.map((issue) => issue.path.join("."))).toEqual(
      expect.arrayContaining(["currency", "lineItems.0.description", "lineItems.0.quantity"]),
    );
  });
});
