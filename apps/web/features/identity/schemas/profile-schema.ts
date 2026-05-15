import { z } from "zod/v4";

export const notificationPreferencesSchema = z.object({
  "requisition.submitted": z.object({ inApp: z.boolean() }),
  "requisition.changes_requested": z.object({ inApp: z.boolean() }),
  "requisition.resubmitted": z.object({ inApp: z.boolean() }),
  "requisition.withdrawn": z.object({ inApp: z.boolean() }),
  "requisition.cancelled": z.object({ inApp: z.boolean() }),
  "attachment.uploaded": z.object({ inApp: z.boolean() }),
  "collaboration.mentioned": z.object({ inApp: z.boolean() }),
  "system.announcement": z.object({ inApp: z.boolean() }),
});

export const defaultNotificationPreferences = {
  "requisition.submitted": { inApp: true },
  "requisition.changes_requested": { inApp: true },
  "requisition.resubmitted": { inApp: true },
  "requisition.withdrawn": { inApp: true },
  "requisition.cancelled": { inApp: true },
  "attachment.uploaded": { inApp: true },
  "collaboration.mentioned": { inApp: true },
  "system.announcement": { inApp: true },
} satisfies z.infer<typeof notificationPreferencesSchema>;

export const profileSchema = z.object({
  name: z.string().min(1, "Name is required.").max(255),
  avatarUrl: z.union([
    z.string().url("Enter a valid URL.").max(2048),
    z.literal(""),
    z.null(),
  ]),
  timezone: z.string().min(1, "Timezone is required.").max(64),
  locale: z.string().min(2).max(12),
  theme: z.enum(["light", "dark", "system"]),
  notificationPreferences: notificationPreferencesSchema,
});

export type ProfileFormValues = z.infer<typeof profileSchema>;
