import { z } from "zod";

const requiredText = (message: string) => z.string().trim().min(1, message);

export const requisitionLineItemSchema = z.object({
  id: z.string().optional(),
  name: requiredText("Item name is required before submission."),
  description: z.string().optional(),
  quantity: z.coerce.number().positive("Quantity must be greater than 0."),
  unit: requiredText("Unit is required before submission."),
  estimatedUnitPrice: z.coerce.number().positive("Estimated unit price must be greater than 0."),
  currency: requiredText("Currency is required before submission."),
  estimatedLineTotal: z.number().optional(),
});

export const requisitionDraftSchema = z.object({
  title: requiredText("Title is required."),
  businessJustification: z.string().optional().default(""),
  neededByDate: z.string().optional().default(""),
  department: z.string().optional().default(""),
  projectId: z.string().optional().default(""),
  costCenter: z.string().optional().default(""),
  deliveryLocation: z.string().optional().default(""),
  currency: z.string().optional().default("MYR"),
  lineItems: z.array(
    z.object({
      id: z.string().optional(),
      name: z.string().default(""),
      description: z.string().optional(),
      quantity: z.coerce.number().default(1),
      unit: z.string().default("each"),
      estimatedUnitPrice: z.coerce.number().default(0),
      currency: z.string().default("MYR"),
      estimatedLineTotal: z.number().optional(),
    }),
  ),
});

export const requisitionSubmitSchema = requisitionDraftSchema.extend({
  businessJustification: requiredText("Business justification is required before submission."),
  neededByDate: requiredText("Needed-by date is required before submission."),
  currency: requiredText("Currency is required before submission."),
  lineItems: z.array(requisitionLineItemSchema).min(1, "Add at least one line item before submission."),
});

export type RequisitionSubmitInput = z.infer<typeof requisitionSubmitSchema>;
