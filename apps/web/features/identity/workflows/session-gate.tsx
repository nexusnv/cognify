"use client";

import Link from "next/link";
import { useEffect, useRef } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useCurrentUser } from "../hooks/use-current-user";
import { getStoredActiveTenantId, storeActiveTenantId } from "../api/identity-api";
import { TenantSelection } from "../components/tenant-selection";
import type React from "react";

function SignInRequired() {
  const router = useRouter();
  const pathname = usePathname() || "/dashboard";
  const searchParams = useSearchParams();
  const search = searchParams.toString();
  const next = search ? `${pathname}?${search}` : pathname;
  const loginHref = `/login?next=${encodeURIComponent(next)}`;

  useEffect(() => {
    router.replace(loginHref);
  }, [loginHref, router]);

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
  if (!error || typeof error !== "object") return false;

  const status = (error as { status?: unknown }).status;
  if (status === 401 || status === 403) return true;

  const code = getApiErrorCode(error);
  return code === "unauthenticated" || code === "forbidden";
}

function getApiErrorCode(error: object): unknown {
  if (!("error" in error)) {
    return undefined;
  }

  const envelope = error.error;
  if (!envelope || typeof envelope !== "object" || !("code" in envelope)) {
    return undefined;
  }

  return envelope.code;
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
  const synced = useRef(false);

  const context = data?.data;

  useEffect(() => {
    if (synced.current) return;
    if (!context) return;

    const tenantId = context.activeTenant?.id ?? (context.tenants.length === 1 ? context.tenants[0]?.id : null);
    if (tenantId && !getStoredActiveTenantId()) {
      storeActiveTenantId(tenantId);
    }
    synced.current = true;
  }, [context]);

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

  if (!context) {
    return <SignInRequired />;
  }

  if (!context.activeTenant && context.tenants.length > 1) {
    return <TenantSelection />;
  }

  return <>{children}</>;
}
