"use client";

import { Alert, AlertDescription, Card, CardContent, CardDescription, CardHeader } from "@cognify/ui";
import { useCurrentUser } from "../hooks/use-current-user";
import { ProfileForm } from "../forms/profile-form";

export function AccountSettingsPage() {
  const { data, isError, isLoading } = useCurrentUser();

  if (isLoading) {
    return (
      <Card className="mx-auto max-w-lg">
        <CardContent className="p-6 text-sm text-muted-foreground">Loading...</CardContent>
      </Card>
    );
  }

  if (isError) {
    return (
      <Alert variant="destructive" className="mx-auto max-w-lg">
        <AlertDescription>Failed to load profile.</AlertDescription>
      </Alert>
    );
  }

  const profile = data?.data.user;
  if (!profile) {
    return (
      <Card className="mx-auto max-w-lg">
        <CardContent className="p-6 text-sm text-muted-foreground">No profile data.</CardContent>
      </Card>
    );
  }

  return (
    <div className="mx-auto max-w-lg">
      <Card>
        <CardHeader>
          <h1 className="text-2xl font-semibold">Account settings</h1>
          <CardDescription>Update your profile and notification preferences.</CardDescription>
        </CardHeader>
        <CardContent>
          <ProfileForm profile={profile} />
        </CardContent>
      </Card>
    </div>
  );
}
