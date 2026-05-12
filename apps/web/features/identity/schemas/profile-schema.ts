import { z } from "zod";

export const profileSchema = z.object({
  name: z.string().min(1, "Name is required.").max(255),
  avatarUrl: z
    .string()
    .url("Enter a valid URL.")
    .max(2048)
    .nullable()
    .or(z.literal("")),
  timezone: z.string().min(1, "Timezone is required.").max(64),
  locale: z.string().min(2).max(12),
  theme: z.enum(["light", "dark", "system"]),
});

export type ProfileFormValues = z.infer<typeof profileSchema>;