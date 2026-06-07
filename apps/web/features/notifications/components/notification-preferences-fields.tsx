"use client";

import type { UseFormSetValue } from "react-hook-form";
import { Checkbox } from "@cognify/ui";
import type { ProfileFormValues } from "@/features/identity/schemas/profile-schema";

const preferenceFields = [
  {
    key: "requisition.submitted",
    label: "Requisition submitted",
    description: "Notify me when requisitions are ready for procurement review.",
  },
  {
    key: "requisition.changes_requested",
    label: "Changes requested",
    description: "Notify me when a requisition needs requester correction.",
  },
  {
    key: "requisition.resubmitted",
    label: "Requisition resubmitted",
    description: "Notify me when corrected requisitions return to review.",
  },
  {
    key: "requisition.withdrawn",
    label: "Requisition withdrawn",
    description: "Notify me when a requester stops a requisition.",
  },
  {
    key: "requisition.cancelled",
    label: "Requisition cancelled",
    description: "Notify me when an admin cancels a requisition.",
  },
  {
    key: "attachment.uploaded",
    label: "Evidence uploaded",
    description:
      "Notify me when evidence is added to my requisitions by another user.",
  },
  {
    key: "collaboration.mentioned",
    label: "Mentions",
    description: "Notify me when a visible collaborator mentions me.",
  },
  {
    key: "system.announcement",
    label: "System announcements",
    description: "Notify me about tenant-level Cognify notices.",
  },
] as const;

export function NotificationPreferencesFields({
  preferences,
  setValue,
}: {
  preferences: ProfileFormValues["notificationPreferences"];
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
              <Checkbox
                role="switch"
                aria-label={field.label}
                checked={preferences[field.key].inApp}
                className="mt-1 h-4 w-4"
                onCheckedChange={(checked) => {
                  setValue(
                    "notificationPreferences",
                    {
                      ...preferences,
                      [field.key]: { inApp: checked === true },
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
