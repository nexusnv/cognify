import { z } from "zod";
import type {
  Quotation,
  QuotationVendorPortal,
  SaveQuotationLineItemRequest,
  SaveQuotationManualEntryRequest,
} from "@cognify/api-client/schemas";

export const quotationLineItemSchema = z.object({
  rfqLineItemId: z.string().optional().nullable(),
  description: z.string().min(1, "Description is required."),
  quantity: z.string().min(1, "Quantity is required."),
  unit: z.string().optional().nullable(),
  unitPrice: z.string().optional().nullable(),
  subtotalAmount: z.string().optional().nullable(),
  taxAmount: z.string().optional().nullable(),
  totalAmount: z.string().optional().nullable(),
  leadTimeDays: z.coerce.number().int().min(0).optional().nullable(),
  manufacturer: z.string().optional().nullable(),
  modelNumber: z.string().optional().nullable(),
  alternateOffered: z.boolean().default(false),
  complianceStatus: z.enum(["compliant", "partial", "non_compliant", "alternate"]).optional().nullable(),
  notes: z.string().optional().nullable(),
});

export const quotationManualEntrySchema = z.object({
  quotationReference: z.string().optional().nullable(),
  quotedAt: z.string().optional().nullable(),
  validUntil: z.string().optional().nullable(),
  currency: z.string().length(3, "Currency must use a 3-letter code.").optional().nullable(),
  subtotalAmount: z.string().optional().nullable(),
  taxAmount: z.string().optional().nullable(),
  freightAmount: z.string().optional().nullable(),
  discountAmount: z.string().optional().nullable(),
  totalAmount: z.string().optional().nullable(),
  paymentTerms: z.string().optional().nullable(),
  deliveryTerms: z.string().optional().nullable(),
  leadTimeDays: z.coerce.number().int().min(0).optional().nullable(),
  warrantyTerms: z.string().optional().nullable(),
  exclusions: z.string().optional().nullable(),
  complianceNotes: z.string().optional().nullable(),
  buyerNotes: z.string().optional().nullable(),
  vendorNotes: z.string().optional().nullable(),
  lineItems: z.array(quotationLineItemSchema),
});

export type QuotationManualEntryFormValues = z.infer<typeof quotationManualEntrySchema>;
type QuotationManualEntrySource = Pick<Quotation | QuotationVendorPortal, "manualEntry" | "lineItems">;

export function formValuesFromQuotation(quotation: QuotationManualEntrySource | null): QuotationManualEntryFormValues {
  return {
    quotationReference: quotation?.manualEntry?.quotationReference ?? "",
    quotedAt: quotation?.manualEntry?.quotedAt ?? "",
    validUntil: quotation?.manualEntry?.validUntil ?? "",
    currency: quotation?.manualEntry?.currency ?? "USD",
    subtotalAmount: quotation?.manualEntry?.subtotalAmount ?? "",
    taxAmount: quotation?.manualEntry?.taxAmount ?? "",
    freightAmount: quotation?.manualEntry?.freightAmount ?? "",
    discountAmount: quotation?.manualEntry?.discountAmount ?? "",
    totalAmount: quotation?.manualEntry?.totalAmount ?? "",
    paymentTerms: quotation?.manualEntry?.paymentTerms ?? "",
    deliveryTerms: quotation?.manualEntry?.deliveryTerms ?? "",
    leadTimeDays: quotation?.manualEntry?.leadTimeDays ?? null,
    warrantyTerms: quotation?.manualEntry?.warrantyTerms ?? "",
    exclusions: quotation?.manualEntry?.exclusions ?? "",
    complianceNotes: quotation?.manualEntry?.complianceNotes ?? "",
    buyerNotes: quotation?.manualEntry?.buyerNotes ?? "",
    vendorNotes: quotation?.manualEntry?.vendorNotes ?? "",
    lineItems: quotation?.lineItems?.length
      ? quotation.lineItems.map((line) => ({
          rfqLineItemId: line.rfqLineItemId,
          description: line.description,
          quantity: line.quantity,
          unit: line.unit,
          unitPrice: line.unitPrice,
          subtotalAmount: line.subtotalAmount,
          taxAmount: line.taxAmount,
          totalAmount: line.totalAmount,
          leadTimeDays: line.leadTimeDays,
          manufacturer: line.manufacturer,
          modelNumber: line.modelNumber,
          alternateOffered: line.alternateOffered,
          complianceStatus: line.complianceStatus,
          notes: line.notes,
        }))
      : [],
  };
}

export function payloadFromFormValues(values: QuotationManualEntryFormValues): SaveQuotationManualEntryRequest {
  return {
    quotationReference: optionalString(values.quotationReference),
    quotedAt: optionalString(values.quotedAt),
    validUntil: optionalString(values.validUntil),
    currency: optionalString(values.currency)?.toUpperCase() ?? null,
    subtotalAmount: optionalString(values.subtotalAmount),
    taxAmount: optionalString(values.taxAmount),
    freightAmount: optionalString(values.freightAmount),
    discountAmount: optionalString(values.discountAmount),
    totalAmount: optionalString(values.totalAmount),
    paymentTerms: optionalString(values.paymentTerms),
    deliveryTerms: optionalString(values.deliveryTerms),
    leadTimeDays: values.leadTimeDays ?? null,
    warrantyTerms: optionalString(values.warrantyTerms),
    exclusions: optionalString(values.exclusions),
    complianceNotes: optionalString(values.complianceNotes),
    buyerNotes: optionalString(values.buyerNotes),
    vendorNotes: optionalString(values.vendorNotes),
    lineItems: values.lineItems.map(payloadLineItemFromFormValues),
  };
}

function payloadLineItemFromFormValues(
  lineItem: QuotationManualEntryFormValues["lineItems"][number],
): SaveQuotationLineItemRequest {
  return {
    rfqLineItemId: optionalString(lineItem.rfqLineItemId),
    description: lineItem.description.trim(),
    quantity: lineItem.quantity.trim(),
    unit: optionalString(lineItem.unit),
    unitPrice: optionalString(lineItem.unitPrice),
    subtotalAmount: optionalString(lineItem.subtotalAmount),
    taxAmount: optionalString(lineItem.taxAmount),
    totalAmount: optionalString(lineItem.totalAmount),
    leadTimeDays: lineItem.leadTimeDays ?? null,
    manufacturer: optionalString(lineItem.manufacturer),
    modelNumber: optionalString(lineItem.modelNumber),
    alternateOffered: lineItem.alternateOffered,
    complianceStatus: lineItem.complianceStatus ?? null,
    notes: optionalString(lineItem.notes),
  };
}

function optionalString(value: string | null | undefined): string | null {
  if (value == null) return null;

  const trimmed = value.trim();
  return trimmed.length > 0 ? trimmed : null;
}
