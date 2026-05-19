"use client";

import Link from "next/link";
import { usePathname, useSearchParams } from "next/navigation";
import { useCurrentUser } from "../hooks/use-current-user";
import { TenantSelection } from "../components/tenant-selection";
import type React from "react";

function SignInRequired() {
  const pathname = usePathname() || "/dashboard";
  const searchParams = useSearchParams();
  const search = searchParams.toString();
  const next = search ? `${pathname}?${search}` : pathname;
  const loginHref = `/login?next=${encodeURIComponent(next)}`;

  return (
    <div className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6">
      <h1 className="text-2xl font-semibold">Sign in required</h1>
      <p className="mt-2 text-sm text-muted-foreground">
        Please{" "}
        <Link href={loginHref} className="text-primary underline">
          sign in
        </Link>{" "}
        to continue.
      </p>
    </div>
  );
}

function isAuthError(error: unknown): boolean {
  if (!error || typeof error !== "object" || !("status" in error)) return false;

  const status = (error as { status?: unknown }).status;
  return status === 401 || status === 403;
}

function WorkspaceUnavailable() {
  return (
    <div className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6">
      <h1 className="text-2xl font-semibold">Workspace unavailable.</h1>
      <p className="mt-2 text-sm text-muted-foreground">
        We could not load your workspace. Try again shortly.
      </p>
    </div>
  );
}

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
    return isAuthError(error) ? <SignInRequired /> : <WorkspaceUnavailable />;
  }

  const context = data?.data;
  if (!context) {
    return <SignInRequired />;
  }

  if (!context.activeTenant && context.tenants.length > 1) {
    return <TenantSelection />;
  }

  return <>{children}</>;
}
