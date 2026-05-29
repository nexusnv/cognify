"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import type { CurrentUserProfile } from "@cognify/api-client/schemas";
import {
  Alert,
  AlertDescription,
  AlertTitle,
  Button,
  Field,
  FieldError,
  FieldLabel,
  Input,
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@cognify/ui";
import { useEffect } from "react";
import { useForm, useWatch } from "react-hook-form";
import { Controller } from "react-hook-form";
import {
  defaultNotificationPreferences,
  profileSchema,
  type ProfileFormValues,
} from "../schemas/profile-schema";
import { useProfileUpdate } from "../hooks/use-profile-update";
import { FormField } from "@/components/forms/form-field";
import { NotificationPreferencesFields } from "@/features/notifications/components/notification-preferences-fields";

function getProfileFormValues(profile: CurrentUserProfile): ProfileFormValues {
  return {
    name: profile.name,
    avatarUrl: profile.avatarUrl || "",
    timezone: profile.timezone,
    locale: profile.locale,
    theme: profile.theme,
    notificationPreferences: {
      ...defaultNotificationPreferences,
      ...(profile.notificationPreferences ?? {}),
    },
  };
}

export function ProfileForm({
  profile,
}: {
  profile: CurrentUserProfile;
}) {
  const updateMutation = useProfileUpdate();

  const {
    register,
    control,
    setValue,
    handleSubmit,
    reset,
    formState: { errors, isDirty },
  } = useForm<ProfileFormValues>({
    resolver: zodResolver(profileSchema),
    defaultValues: getProfileFormValues(profile),
  });

  useEffect(() => {
    reset(getProfileFormValues(profile));
  }, [profile, reset]);

  const onSubmit = async (values: ProfileFormValues) => {
    await updateMutation.mutateAsync(values);
  };

  const notificationPreferences = useWatch({
    control,
    name: "notificationPreferences",
  }) ?? defaultNotificationPreferences;

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <FormField htmlFor="name" label="Name" error={errors.name?.message} required>
        <Input {...register("name")} />
      </FormField>

      <FormField htmlFor="avatarUrl" label="Avatar URL" error={errors.avatarUrl?.message}>
        <Input {...register("avatarUrl")} />
      </FormField>

      <FormField htmlFor="timezone" label="Timezone" error={errors.timezone?.message} required>
        <Input {...register("timezone")} />
      </FormField>

      <FormField htmlFor="locale" label="Locale" error={errors.locale?.message} required>
        <Input {...register("locale")} />
      </FormField>

      <Controller
        control={control}
        name="theme"
        render={({ field }) => (
          <Field>
            <FieldLabel htmlFor="theme">Theme</FieldLabel>
            <Select value={field.value} onValueChange={field.onChange}>
              <SelectTrigger
                id="theme"
                className="w-full"
                aria-invalid={Boolean(errors.theme)}
                aria-describedby={errors.theme ? "theme-error" : undefined}
              >
                <SelectValue placeholder="Select a theme" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="light">Light</SelectItem>
                <SelectItem value="dark">Dark</SelectItem>
                <SelectItem value="system">System</SelectItem>
              </SelectContent>
            </Select>
            <FieldError id="theme-error">{errors.theme?.message}</FieldError>
          </Field>
        )}
      />

      <NotificationPreferencesFields
        preferences={notificationPreferences}
        setValue={setValue}
      />

      <Button
        type="submit"
        disabled={updateMutation.isPending || !isDirty}
        className="w-full"
      >
        {updateMutation.isPending ? "Saving..." : "Save profile"}
      </Button>

      {updateMutation.isSuccess && (
        <Alert>
          <AlertTitle>Profile saved</AlertTitle>
          <AlertDescription>Your profile changes were saved.</AlertDescription>
        </Alert>
      )}

      {updateMutation.error && (
        <Alert variant="destructive">
          <AlertDescription>Failed to save profile.</AlertDescription>
        </Alert>
      )}
    </form>
  );
}
