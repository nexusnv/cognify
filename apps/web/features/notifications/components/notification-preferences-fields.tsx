"use client";

import type { UseFormSetValue } from "react-hook-form";
import type { ProfileFormValues } from "@/features/identity/schemas/profile-schema";
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Checkbox,
  Field,
  FieldContent,
  FieldDescription,
  FieldGroup,
  FieldLabel,
  FieldLegend,
  FieldSet,
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
  Switch,
} from "@cognify/ui";

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

const presetOptions = [
  { value: "custom", label: "Custom" },
  { value: "all", label: "All notifications" },
  { value: "procurement", label: "Procurement focus" },
] as const;

type NotificationPreferenceKey = (typeof preferenceFields)[number]["key"];

export function NotificationPreferencesFields({
  preferences,
  setValue,
}: {
  preferences: ProfileFormValues["notificationPreferences"];
  setValue: UseFormSetValue<ProfileFormValues>;
}) {
  const allEnabled = preferenceFields.every((field) => preferences[field.key].inApp);
  const preset = inferPreset(preferences);

  function updatePreferences(next: ProfileFormValues["notificationPreferences"]) {
    setValue("notificationPreferences", next, { shouldDirty: true, shouldValidate: true });
  }

  function updateAll(enabled: boolean) {
    const next = Object.fromEntries(
      preferenceFields.map((field) => [field.key, { inApp: enabled }]),
    ) as ProfileFormValues["notificationPreferences"];
    updatePreferences(next);
  }

  function applyPreset(value: "custom" | "all" | "procurement") {
    if (value === "all") {
      updateAll(true);
      return;
    }

    if (value === "procurement") {
      const next = Object.fromEntries(
        preferenceFields.map((field) => [
          field.key,
          {
            inApp:
              field.key.startsWith("requisition.") ||
              field.key === "attachment.uploaded" ||
              field.key === "system.announcement",
          },
        ]),
      ) as ProfileFormValues["notificationPreferences"];
      updatePreferences(next);
    }
  }

  return (
    <Card>
      <CardHeader className="gap-2">
        <CardTitle className="text-base">Notifications</CardTitle>
        <CardDescription>Choose which in-app workflow cues you receive.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
        <FieldSet>
          <FieldLegend className="sr-only">Notification preset</FieldLegend>
          <FieldGroup>
            <Field orientation="horizontal" className="items-center justify-between rounded-md bg-muted/30 p-3">
              <FieldContent className="gap-1">
                <FieldLabel className="w-auto">Preset</FieldLabel>
                <FieldDescription>Apply a common preference set to this workspace profile.</FieldDescription>
              </FieldContent>
              <Select value={preset} onValueChange={(value) => applyPreset(value as "custom" | "all" | "procurement")}>
                <SelectTrigger className="w-56">
                  <SelectValue placeholder="Select preset" />
                </SelectTrigger>
                <SelectContent>
                  {presetOptions.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>

            <Field orientation="horizontal" className="items-center justify-between rounded-md bg-muted/30 p-3">
              <FieldContent className="gap-1">
                <FieldLabel className="w-auto">Enable all in-app notifications</FieldLabel>
                <FieldDescription>Use one switch to turn the entire list on or off.</FieldDescription>
              </FieldContent>
              <Checkbox
                checked={allEnabled}
                onCheckedChange={(checked) => updateAll(Boolean(checked))}
                aria-label="Enable all in-app notifications"
              />
            </Field>
          </FieldGroup>
        </FieldSet>

        <FieldSet>
          <FieldLegend>Notification cues</FieldLegend>
          <FieldGroup>
            {preferenceFields.map((field) => {
              return (
                <Field key={field.key} orientation="horizontal" className="items-start rounded-md bg-muted/30 p-3">
                  <FieldContent>
                    <FieldLabel>{field.label}</FieldLabel>
                    <FieldDescription>{field.description}</FieldDescription>
                  </FieldContent>
                  <Switch
                    aria-label={field.label}
                    checked={preferences[field.key].inApp}
                    onCheckedChange={(checked) => {
                      updatePreferences({
                        ...preferences,
                        [field.key]: { inApp: checked },
                      });
                    }}
                  />
                </Field>
              );
            })}
          </FieldGroup>
        </FieldSet>

        <div className="flex flex-wrap items-center gap-2">
          <Badge variant="secondary">{allEnabled ? "All enabled" : "Custom selection"}</Badge>
          <Button type="button" variant="outline" onClick={() => updateAll(true)}>
            Enable all
          </Button>
          <Button type="button" variant="ghost" onClick={() => updateAll(false)}>
            Disable all
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

function inferPreset(preferences: ProfileFormValues["notificationPreferences"]) {
  const allEnabled = preferenceFields.every((field) => preferences[field.key].inApp);
  if (allEnabled) return "all";

  const procurementEnabled = preferenceFields.every((field) => {
    const enabled =
      field.key.startsWith("requisition.") ||
      field.key === "attachment.uploaded" ||
      field.key === "system.announcement";
    return preferences[field.key].inApp === enabled;
  });

  return procurementEnabled ? "procurement" : "custom";
}
