import { z } from "zod";
import { UpdateRfqInvitationStatusRequestStatus } from "@cognify/api-client/schemas";

export const rfqInvitationCreateSchema = z.object({
  vendorIds: z
    .array(z.string().min(1))
    .min(1, "Select at least one vendor.")
    .max(25, "You can invite at most 25 vendors at a time."),
});

export type RfqInvitationCreateValues = z.infer<typeof rfqInvitationCreateSchema>;

export const rfqInvitationCancelSchema = z.object({
  cancelReason: z
    .string()
    .trim()
    .min(1, "Cancel reason is required.")
    .max(5000, "Cancel reason is too long."),
});

export type RfqInvitationCancelValues = z.infer<typeof rfqInvitationCancelSchema>;

export const rfqInvitationStatusSchema = z.object({
  status: z.enum([
    UpdateRfqInvitationStatusRequestStatus.acknowledged,
    UpdateRfqInvitationStatusRequestStatus.declined,
    UpdateRfqInvitationStatusRequestStatus.expired,
  ] as const),
});

export type RfqInvitationStatusValues = z.infer<typeof rfqInvitationStatusSchema>;
