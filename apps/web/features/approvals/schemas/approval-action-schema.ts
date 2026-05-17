import { z } from "zod";

export const approvalActionSchema = z.object({
  decision: z.enum(["approve", "reject", "request_changes"]),
  reason: z.string().max(2000).optional(),
});
