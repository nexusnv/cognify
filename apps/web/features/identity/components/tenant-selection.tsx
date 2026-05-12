"use client";

import { useCurrentUser } from "../hooks/use-current-user";
import { setCurrentTenant } from "../api/identity-api";
import { useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

export function TenantSelection() {
  const { data } = useCurrentUser();
  const queryClient = useQueryClient();
  const [selectingTenantId, setSelectingTenantId] = useState<string | null>(null);

  const tenants = data?.data.tenants ?? [];

  if (tenants.length <= 1) return null;

  const handleSelect = async (tenantId: string) => {
    if (selectingTenantId) return;

    setSelectingTenantId(tenantId);
    try {
      await setCurrentTenant(tenantId);
      queryClient.invalidateQueries({ queryKey: ["identity", "current-user"] });
    } catch {
      // handled by caller
    } finally {
      setSelectingTenantId(null);
    }
  };

  return (
    <div className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6">
      <h1 className="text-2xl font-semibold" role="heading" aria-level={1}>
        Choose workspace
      </h1>
      <p className="mt-2 text-sm text-muted-foreground">
        You have access to multiple workspaces. Select one to continue.
      </p>
      <div className="mt-6 flex flex-col gap-3">
        {tenants.map((tenant) => (
          <button
            key={tenant.id}
            onClick={() => handleSelect(tenant.id)}
            disabled={selectingTenantId !== null}
            aria-busy={selectingTenantId === tenant.id}
            className="rounded-md border bg-card px-4 py-3 text-left text-sm font-medium hover:bg-accent disabled:cursor-not-allowed disabled:opacity-60"
          >
            {selectingTenantId === tenant.id ? "Selecting..." : tenant.name}
          </button>
        ))}
      </div>
    </div>
  );
}
