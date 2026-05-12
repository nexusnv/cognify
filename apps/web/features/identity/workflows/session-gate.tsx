"use client";

import Link from "next/link";
import { useCurrentUser } from "../hooks/use-current-user";
import { TenantSelection } from "../components/tenant-selection";
import type React from "react";

export function SessionGate({ children }: { children: React.ReactNode }) {
  const { data, isLoading, error } = useCurrentUser();

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <p className="text-sm text-muted-foreground">Loading...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6">
        <h1 className="text-2xl font-semibold">Sign in required</h1>
        <p className="mt-2 text-sm text-muted-foreground">
          Please{" "}
          <Link href="/login" className="text-primary underline">
            sign in
          </Link>{" "}
          to continue.
        </p>
      </div>
    );
  }

  const context = data?.data;
  if (!context) {
    return (
      <div className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6">
        <h1 className="text-2xl font-semibold">Sign in required</h1>
        <p className="mt-2 text-sm text-muted-foreground">
          Please{" "}
          <Link href="/login" className="text-primary underline">
            sign in
          </Link>{" "}
          to continue.
        </p>
      </div>
    );
  }

  if (!context.activeTenant && context.tenants.length > 1) {
    return <TenantSelection />;
  }

  return <>{children}</>;
}