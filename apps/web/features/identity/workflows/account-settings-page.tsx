"use client";

import { useCurrentUser } from "../hooks/use-current-user";
import { ProfileForm } from "../forms/profile-form";

export function AccountSettingsPage() {
  const { data, isError, isLoading } = useCurrentUser();

  if (isLoading) {
    return <p className="text-sm text-muted-foreground">Loading...</p>;
  }

  if (isError) {
    return <p className="text-sm text-red-600">Failed to load profile.</p>;
  }

  const profile = data?.data.user;
  if (!profile) {
    return <p className="text-sm text-muted-foreground">No profile data.</p>;
  }

  return (
    <div className="mx-auto max-w-lg">
      <h1 className="text-2xl font-semibold">Account settings</h1>
      <div className="mt-6">
        <ProfileForm profile={profile} />
      </div>
    </div>
  );
}
