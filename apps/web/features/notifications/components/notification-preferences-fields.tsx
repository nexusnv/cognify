"use client";

import type {
  FieldPath,
  UseFormRegister,
  UseFormSetValue,
} from "react-hook-form";
import type { ProfileFormValues } from "@/features/identity/schemas/profile-schema";

const preferenceFields = [
  {
    key: "requisition.submitted",
    label: "Requisition submitted",
    description: "Notify me when requisitions are ready for procurement review.",
  },
  {
    key: "attachment.uploaded",
    label: "Evidence uploaded",
    description:
      "Notify me when evidence is added to my requisitions by another user.",
  },
  {
    key: "system.announcement",
    label: "System announcements",
    description: "Notify me about tenant-level Cognify notices.",
  },
] as const;

export function NotificationPreferencesFields({
  preferences,
  register,
  setValue,
}: {
  preferences: ProfileFormValues["notificationPreferences"];
  register: UseFormRegister<ProfileFormValues>;
  setValue: UseFormSetValue<ProfileFormValues>;
}) {
  return (
    <section
      className="space-y-3 rounded-lg border p-4"
      aria-labelledby="notification-preferences-title"
    >
      <div>
        <h2
          id="notification-preferences-title"
          className="text-sm font-semibold"
        >
          Notifications
        </h2>
        <p className="text-sm text-muted-foreground">
          Choose which in-app workflow cues you receive.
        </p>
      </div>
      <div className="grid gap-3">
        {preferenceFields.map((field) => {
          const fieldName =
            `notificationPreferences.${field.key}.inApp` as FieldPath<ProfileFormValues>;
          const registration = register(fieldName);

          return (
            <label
              key={field.key}
              className="flex items-start justify-between gap-4 rounded-md border px-3 py-2"
            >
              <span>
                <span className="block text-sm font-medium">{field.label}</span>
                <span className="block text-xs text-muted-foreground">
                  {field.description}
                </span>
              </span>
              <input
                type="checkbox"
                role="switch"
                aria-label={field.label}
                defaultChecked={preferences[field.key].inApp}
                className="mt-1 h-4 w-4"
                {...registration}
                onChange={(event) => {
                  void registration.onChange(event);
                  setValue(
                    "notificationPreferences",
                    {
                      ...preferences,
                      [field.key]: { inApp: event.target.checked },
                    },
                    { shouldDirty: true, shouldValidate: true },
                  );
                }}
              />
            </label>
          );
        })}
      </div>
    </section>
  );
}
