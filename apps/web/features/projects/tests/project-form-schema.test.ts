import { describe, expect, it } from "vitest";
import { projectFormSchema } from "../schemas/project-form-schema";

describe("projectFormSchema", () => {
  it("accepts a valid project form", () => {
    const parsed = projectFormSchema.safeParse({
      name: "Office refresh",
      charter: "Refresh workstations.",
      ownerId: "12",
      budgetAmount: "25000.00",
      currency: "MYR",
      department: "Operations",
      costCenter: "OPS-100",
      targetStartDate: "2026-06-01",
      targetCompletionDate: "2026-09-30",
    });

    expect(parsed.success).toBe(true);
  });

  it("rejects completion dates before start dates", () => {
    const parsed = projectFormSchema.safeParse({
      name: "Office refresh",
      charter: "",
      ownerId: "12",
      budgetAmount: "25000.00",
      currency: "MYR",
      department: "",
      costCenter: "",
      targetStartDate: "2026-09-30",
      targetCompletionDate: "2026-06-01",
    });

    expect(parsed.success).toBe(false);
  });
});
