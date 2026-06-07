"use client";

import { Alert, AlertDescription, Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
import { useCurrentUser } from "../hooks/use-current-user";
import { ProfileForm } from "../forms/profile-form";

export function AccountSettingsPage() {
  const { data, isError, isLoading } = useCurrentUser();

  if (isLoading) {
    return (
      <div className="mx-auto max-w-4xl">
        <Card>
          <CardContent className="py-6 text-sm text-muted-foreground">Loading...</CardContent>
        </Card>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="mx-auto max-w-4xl">
        <Alert variant="destructive">
          <AlertDescription>Failed to load profile.</AlertDescription>
        </Alert>
      </div>
    );
  }

  const profile = data?.data.user;
  if (!profile) {
    return (
      <div className="mx-auto max-w-4xl">
        <Alert>
          <AlertDescription>No profile data.</AlertDescription>
        </Alert>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <Card className="py-0">
        <CardHeader className="border-b bg-muted/30">
          <CardTitle className="text-2xl">Account settings</CardTitle>
        </CardHeader>
        <CardContent className="py-4 text-sm text-muted-foreground">
          Manage your profile, workspace theme preference, and notification routing.
        </CardContent>
      </Card>
      <div>
        <ProfileForm profile={profile} />
      </div>
    </div>
  );
}
