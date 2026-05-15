import { z } from "zod";

export const projectFormSchema = z
  .object({
    name: z.string().min(1, "Project name is required").max(255),
    charter: z.string().max(5000).optional().default(""),
    ownerId: z.string().min(1, "Owner is required"),
    budgetAmount: z.string().regex(/^\d+(\.\d{1,2})?$/, "Budget must be a valid amount"),
    currency: z.string().length(3, "Currency must be a 3-letter code"),
    department: z.string().max(255).optional().default(""),
    costCenter: z.string().max(255).optional().default(""),
    targetStartDate: z.string().optional().default(""),
    targetCompletionDate: z.string().optional().default(""),
  })
  .refine(
    (value) => {
      if (!value.targetStartDate || !value.targetCompletionDate) return true;
      return value.targetCompletionDate >= value.targetStartDate;
    },
    {
      message: "Target completion date cannot be before target start date",
      path: ["targetCompletionDate"],
    },
  );
