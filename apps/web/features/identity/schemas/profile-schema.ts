import { NotificationEventType } from "@cognify/api-client";
import { z } from "zod/v4";

const notificationPreferenceSchema = z.object({ inApp: z.boolean() });
const notificationPreferenceShape = Object.fromEntries(
  Object.values(NotificationEventType).map((eventType) => [
    eventType,
    notificationPreferenceSchema,
  ]),
) as Record<NotificationEventType, typeof notificationPreferenceSchema>;

export const notificationPreferencesSchema = z.object(notificationPreferenceShape);

export const defaultNotificationPreferences = Object.fromEntries(
  Object.values(NotificationEventType).map((eventType) => [eventType, { inApp: true }]),
) as z.infer<typeof notificationPreferencesSchema>;

export const profileSchema = z.object({
  name: z.string().min(1, "Name is required.").max(255),
  avatarUrl: z.union([z.string().url("Enter a valid URL.").max(2048), z.literal(""), z.null()]),
  timezone: z.string().min(1, "Timezone is required.").max(64),
  locale: z.string().min(2).max(12),
  theme: z.enum(["light", "dark", "system"]),
  notificationPreferences: notificationPreferencesSchema,
});

export type ProfileFormValues = z.infer<typeof profileSchema>;
