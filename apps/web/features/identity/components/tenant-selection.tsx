"use client";

import { Button, Card, CardContent, CardDescription, CardHeader } from "@cognify/ui";
import { useCurrentUser } from "../hooks/use-current-user";
import { setCurrentTenant } from "../api/identity-api";
import { useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { LogOut } from "lucide-react";
import { useRouter } from "next/navigation";
import { useLogout } from "../hooks/use-logout";

export function TenantSelection() {
  const { data } = useCurrentUser();
  const queryClient = useQueryClient();
  const router = useRouter();
  const logoutMutation = useLogout();
  const [selectingTenantId, setSelectingTenantId] = useState<string | null>(null);

  const tenants = data?.data.tenants ?? [];
  const userName = data?.data.user.name?.trim() || "a Cognify user";

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

  const handleSignOut = () => {
    logoutMutation.mutate(undefined, {
      onSuccess: () => router.replace("/login"),
    });
  };

  return (
    <div className="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-6">
      <Card className="border shadow-sm">
        <CardHeader className="gap-2 px-6 pt-6">
          <h1 className="text-2xl font-semibold leading-none tracking-tight">Choose workspace</h1>
          <CardDescription className="text-sm">
            Signed in as {userName}. Select the workspace you want to use for this session.
          </CardDescription>
        </CardHeader>
        <CardContent className="grid gap-4 px-6 pb-6">
          <div className="grid gap-3">
            {tenants.map((tenant) => (
              <Button
                key={tenant.id}
                type="button"
                variant="outline"
                size="lg"
                onClick={() => handleSelect(tenant.id)}
                disabled={selectingTenantId !== null || logoutMutation.isPending}
                aria-busy={selectingTenantId === tenant.id}
                aria-label={`${tenant.name} ${tenant.role}`}
                className="h-auto justify-between whitespace-normal px-4 py-3 text-left"
              >
                <span>
                  <span className="block font-medium">
                    {selectingTenantId === tenant.id ? "Selecting..." : tenant.name}
                  </span>
                  <span className="mt-1 block text-xs font-normal text-muted-foreground">
                    {tenant.role}
                  </span>
                </span>
              </Button>
            ))}
          </div>
          <Button
            type="button"
            variant="ghost"
            className="justify-start text-muted-foreground"
            disabled={logoutMutation.isPending || selectingTenantId !== null}
            onClick={handleSignOut}
          >
            <LogOut className="size-4" aria-hidden="true" />
            {logoutMutation.isPending ? "Signing out" : "Sign out"}
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
