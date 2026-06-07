"use client";

import {
  Alert,
  AlertAction,
  AlertDescription,
  AlertTitle,
  Button,
  Card,
  CardContent,
  Skeleton,
} from "@cognify/ui";
import { DemoDatasetSummary } from "../components/demo-dataset-summary";
import { SystemCheckList } from "../components/system-check-list";
import { SystemStatusSummary } from "../components/system-status-summary";
import { useSystemStatus } from "../hooks/use-system-status";
import { useCurrentUser } from "@/features/identity/hooks/use-current-user";

export function SystemStatusPage() {
  const currentUserQuery = useCurrentUser();
  const tenantId = currentUserQuery.data?.data.activeTenant?.id ?? null;
  const query = useSystemStatus(tenantId);
  const status = query.data?.data;

  if (currentUserQuery.isLoading || query.isLoading) {
    return (
      <Card role="status" className="mx-auto w-full max-w-5xl">
        <CardContent className="space-y-4">
          <Skeleton className="h-8 w-48" />
          <Skeleton className="h-24 w-full" />
          <Skeleton className="h-40 w-full" />
        </CardContent>
      </Card>
    );
  }

  if (tenantId === null) {
    return (
      <Alert>
        <AlertTitle>No active workspace selected.</AlertTitle>
        <AlertDescription>Select a workspace before checking system readiness.</AlertDescription>
      </Alert>
    );
  }

  if (query.isError || !status) {
    return (
      <Alert variant="destructive">
        <AlertTitle>System status could not be loaded.</AlertTitle>
        <AlertDescription>Retry after the readiness endpoint is available.</AlertDescription>
        <AlertAction>
          <Button variant="outline" onClick={() => query.refetch()}>
            Retry
          </Button>
        </AlertAction>
      </Alert>
    );
  }

  return (
    <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-6">
      <SystemStatusSummary status={status} />
      <SystemCheckList checks={status.checks} />
      <DemoDatasetSummary demo={status.demo} />
    </div>
  );
}
