"use client";

import { Controller, useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  Alert,
  AlertDescription,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Field,
  FieldContent,
  FieldDescription,
  FieldError,
  FieldLabel,
  Form,
  Input,
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@cognify/ui";
import {
  defaultNotificationPreferences,
  profileSchema,
  type ProfileFormValues,
} from "../schemas/profile-schema";
import { useProfileUpdate } from "../hooks/use-profile-update";
import type { CurrentUserProfile } from "@cognify/api-client/schemas";
import { useEffect, useRef } from "react";
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
  const { isError, isSuccess, reset: resetMutation } = updateMutation;

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
  const settledValuesRef = useRef<string | null>(null);

  useEffect(() => {
    settledValuesRef.current = null;
    resetMutation();
    reset(getProfileFormValues(profile));
  }, [profile, reset, resetMutation]);

  const onSubmit = async (values: ProfileFormValues) => {
    try {
      await updateMutation.mutateAsync(values);
    } catch {
      // mutation state drives the inline error alert
    }
  };

  const notificationPreferences = useWatch({
    control,
    name: "notificationPreferences",
  }) ?? defaultNotificationPreferences;
  const watchedValues = useWatch({ control });
  const hadSettledStateRef = useRef(false);

  useEffect(() => {
    const isSettled = isSuccess || isError;

    if (isSettled && !hadSettledStateRef.current) {
      settledValuesRef.current = JSON.stringify(watchedValues);
      hadSettledStateRef.current = true;
      return;
    }

    if (!isSettled) {
      settledValuesRef.current = null;
      hadSettledStateRef.current = false;
    }
  }, [isError, isSuccess, watchedValues]);

  useEffect(() => {
    if (!(isSuccess || isError)) {
      return;
    }

    const settledValues = settledValuesRef.current;
    if (!settledValues) {
      return;
    }

    if (JSON.stringify(watchedValues) !== settledValues) {
      settledValuesRef.current = null;
      resetMutation();
    }
  }, [isError, isSuccess, resetMutation, watchedValues]);

  return (
    <Form onSubmit={handleSubmit(onSubmit)} className="space-y-5">
      <Card className="py-0">
        <CardHeader className="border-b bg-muted/30">
          <CardTitle>Profile details</CardTitle>
          <CardDescription>
            Update the account information shown across your Cognify workspace.
          </CardDescription>
        </CardHeader>
        <CardContent className="grid gap-4 py-4">
          <Field data-invalid={Boolean(errors.name)}>
            <FieldLabel htmlFor="name">Name</FieldLabel>
            <FieldContent>
              <Input
                id="name"
                {...register("name")}
                aria-invalid={Boolean(errors.name)}
                aria-describedby={errors.name ? "profile-name-error" : undefined}
              />
              <FieldError id="profile-name-error" errors={[errors.name]} />
            </FieldContent>
          </Field>

          <Field data-invalid={Boolean(errors.avatarUrl)}>
            <FieldLabel htmlFor="avatarUrl">Avatar URL</FieldLabel>
            <FieldContent>
              <Input
                id="avatarUrl"
                {...register("avatarUrl")}
                aria-invalid={Boolean(errors.avatarUrl)}
                aria-describedby={
                  errors.avatarUrl ? "profile-avatar-error" : "profile-avatar-description"
                }
              />
              <FieldDescription id="profile-avatar-description">
                Optional public image URL for your account menu and workflow comments.
              </FieldDescription>
              <FieldError id="profile-avatar-error" errors={[errors.avatarUrl]} />
            </FieldContent>
          </Field>

          <div className="grid gap-4 md:grid-cols-2">
            <Field data-invalid={Boolean(errors.timezone)}>
              <FieldLabel htmlFor="timezone">Timezone</FieldLabel>
              <FieldContent>
                <Input
                  id="timezone"
                  {...register("timezone")}
                  aria-invalid={Boolean(errors.timezone)}
                  aria-describedby={errors.timezone ? "profile-timezone-error" : undefined}
                />
                <FieldError id="profile-timezone-error" errors={[errors.timezone]} />
              </FieldContent>
            </Field>

            <Field data-invalid={Boolean(errors.locale)}>
              <FieldLabel htmlFor="locale">Locale</FieldLabel>
              <FieldContent>
                <Input
                  id="locale"
                  {...register("locale")}
                  aria-invalid={Boolean(errors.locale)}
                  aria-describedby={errors.locale ? "profile-locale-error" : undefined}
                />
                <FieldError id="profile-locale-error" errors={[errors.locale]} />
              </FieldContent>
            </Field>
          </div>

          <Field data-invalid={Boolean(errors.theme)}>
            <FieldLabel htmlFor="theme">Theme</FieldLabel>
            <FieldContent>
              <Controller
                control={control}
                name="theme"
                render={({ field }) => (
                  <Select value={field.value} onValueChange={field.onChange}>
                    <SelectTrigger
                      id="theme"
                      aria-label="Theme"
                      aria-invalid={Boolean(errors.theme)}
                      aria-describedby={
                        errors.theme ? "profile-theme-error" : "profile-theme-description"
                      }
                      className="w-full justify-between"
                      onBlur={field.onBlur}
                    >
                      <SelectValue placeholder="Select a theme" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="light">Light</SelectItem>
                      <SelectItem value="dark">Dark</SelectItem>
                      <SelectItem value="system">System</SelectItem>
                    </SelectContent>
                  </Select>
                )}
              />
              <FieldDescription id="profile-theme-description">
                This saves your workspace preference. The header theme shortcut remains
                a local UI toggle until you save here.
              </FieldDescription>
              <FieldError id="profile-theme-error" errors={[errors.theme]} />
            </FieldContent>
          </Field>
        </CardContent>
      </Card>

      <Card className="py-0">
        <CardHeader className="border-b bg-muted/30">
          <CardTitle>Notification routing</CardTitle>
          <CardDescription>
            Choose which in-app workflow cues stay active for your account.
          </CardDescription>
        </CardHeader>
        <CardContent className="py-4">
          <NotificationPreferencesFields
            preferences={notificationPreferences}
            setValue={setValue}
          />
        </CardContent>
      </Card>

      <div className="flex flex-col gap-3">
        <Button
          type="submit"
          disabled={updateMutation.isPending || !isDirty}
          className="w-full sm:w-auto"
        >
          {updateMutation.isPending ? "Saving..." : "Save profile"}
        </Button>

        {isSuccess ? (
          <Alert>
            <AlertDescription>Profile saved</AlertDescription>
          </Alert>
        ) : null}

        {updateMutation.error ? (
          <Alert variant="destructive">
            <AlertDescription>Failed to save profile</AlertDescription>
          </Alert>
        ) : null}
      </div>
    </Form>
  );
}
