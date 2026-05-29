"use client";

import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import type { FormEvent } from "react";
import {
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  Label,
  RadioGroup,
  RadioGroupItem,
  cn,
} from "@cognify/ui";
import { setCurrentTenant } from "../api/identity-api";
import { useCurrentUser } from "../hooks/use-current-user";

export function TenantSelection() {
  const { data } = useCurrentUser();
  const queryClient = useQueryClient();
  const [selectedTenantId, setSelectedTenantId] = useState<string>("");
  const [selectingTenantId, setSelectingTenantId] = useState<string | null>(null);

  const tenants = data?.data.tenants ?? [];

  if (tenants.length <= 1) return null;

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (selectingTenantId || !selectedTenantId) return;

    setSelectingTenantId(selectedTenantId);
    try {
      await setCurrentTenant(selectedTenantId);
      queryClient.invalidateQueries({ queryKey: ["identity", "current-user"] });
    } catch {
      // handled by caller
    } finally {
      setSelectingTenantId(null);
    }
  };

  return (
    <div className="mx-auto flex min-h-screen max-w-lg items-center px-6 py-8">
      <Card className="w-full">
        <CardHeader>
          <h1 className="text-2xl font-semibold">Choose workspace</h1>
          <CardDescription>
            You have access to multiple workspaces. Select one to continue.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="grid gap-4">
            <RadioGroup
              value={selectedTenantId}
              onValueChange={setSelectedTenantId}
              className="gap-3"
            >
              {tenants.map((tenant) => {
                const inputId = `tenant-${tenant.id}`;
                const isSelected = selectedTenantId === tenant.id;
                const isBusy = selectingTenantId === tenant.id;

                return (
                  <div
                    key={tenant.id}
                    className={cn(
                      "flex items-start gap-3 rounded-md border px-4 py-3 transition-colors",
                      isSelected ? "border-primary bg-primary/5" : "bg-card hover:bg-accent",
                      selectingTenantId ? "cursor-not-allowed opacity-60" : "cursor-pointer",
                    )}
                  >
                    <RadioGroupItem
                      id={inputId}
                      value={tenant.id}
                      disabled={selectingTenantId !== null}
                      aria-busy={isBusy}
                      className="mt-1"
                    />
                    <div className="grid gap-1">
                      <Label htmlFor={inputId} className="cursor-pointer text-sm font-medium">
                        {isBusy ? "Selecting..." : tenant.name}
                      </Label>
                      <p className="text-xs text-muted-foreground">{tenant.role}</p>
                    </div>
                  </div>
                );
              })}
            </RadioGroup>

            <Button type="submit" disabled={!selectedTenantId || selectingTenantId !== null}>
              {selectingTenantId ? "Selecting..." : "Continue"}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
