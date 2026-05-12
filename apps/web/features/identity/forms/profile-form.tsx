"use client";

import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  profileSchema,
  type ProfileFormValues,
} from "../schemas/profile-schema";
import { useProfileUpdate } from "../hooks/use-profile-update";
import type { CurrentUserProfile } from "../types/identity-view-model";
import { useEffect } from "react";

export function ProfileForm({
  profile,
}: {
  profile: CurrentUserProfile;
}) {
  const updateMutation = useProfileUpdate();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isDirty },
  } = useForm<ProfileFormValues>({
    resolver: zodResolver(profileSchema),
    defaultValues: {
      name: profile.name,
      avatarUrl: profile.avatarUrl || "",
      timezone: profile.timezone,
      locale: profile.locale,
      theme: profile.theme,
    },
  });

  useEffect(() => {
    reset({
      name: profile.name,
      avatarUrl: profile.avatarUrl || "",
      timezone: profile.timezone,
      locale: profile.locale,
      theme: profile.theme,
    });
  }, [profile, reset]);

  const onSubmit = async (values: ProfileFormValues) => {
    await updateMutation.mutateAsync(values);
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <div>
        <label htmlFor="name" className="block text-sm font-medium">
          Name
        </label>
        <input
          id="name"
          {...register("name")}
          className="mt-1 block w-full rounded-md border px-3 py-2 text-sm"
        />
        {errors.name && (
          <p className="mt-1 text-sm text-red-500">{errors.name.message}</p>
        )}
      </div>

      <div>
        <label htmlFor="avatarUrl" className="block text-sm font-medium">
          Avatar URL
        </label>
        <input
          id="avatarUrl"
          {...register("avatarUrl")}
          className="mt-1 block w-full rounded-md border px-3 py-2 text-sm"
        />
        {errors.avatarUrl && (
          <p className="mt-1 text-sm text-red-500">
            {errors.avatarUrl.message}
          </p>
        )}
      </div>

      <div>
        <label htmlFor="timezone" className="block text-sm font-medium">
          Timezone
        </label>
        <input
          id="timezone"
          {...register("timezone")}
          className="mt-1 block w-full rounded-md border px-3 py-2 text-sm"
        />
        {errors.timezone && (
          <p className="mt-1 text-sm text-red-500">
            {errors.timezone.message}
          </p>
        )}
      </div>

      <div>
        <label htmlFor="locale" className="block text-sm font-medium">
          Locale
        </label>
        <input
          id="locale"
          {...register("locale")}
          className="mt-1 block w-full rounded-md border px-3 py-2 text-sm"
        />
        {errors.locale && (
          <p className="mt-1 text-sm text-red-500">
            {errors.locale.message}
          </p>
        )}
      </div>

      <div>
        <label htmlFor="theme" className="block text-sm font-medium">
          Theme
        </label>
        <select
          id="theme"
          {...register("theme")}
          className="mt-1 block w-full rounded-md border px-3 py-2 text-sm"
        >
          <option value="light">Light</option>
          <option value="dark">Dark</option>
          <option value="system">System</option>
        </select>
        {errors.theme && (
          <p className="mt-1 text-sm text-red-500">
            {errors.theme.message}
          </p>
        )}
      </div>

      <button
        type="submit"
        disabled={updateMutation.isPending || !isDirty}
        className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
      >
        {updateMutation.isPending ? "Saving..." : "Save profile"}
      </button>

      {updateMutation.isSuccess && (
        <p className="text-sm text-green-600">Profile saved</p>
      )}

      {updateMutation.error && (
        <p className="text-sm text-red-500">Failed to save profile</p>
      )}
    </form>
  );
}