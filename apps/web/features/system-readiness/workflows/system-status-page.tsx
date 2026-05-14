"use client";

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

  if (currentUserQuery.isLoading || query.isLoading || tenantId === null) {
    return <div role="status">Loading system status...</div>;
  }

  if (query.isError || !status) {
    return <div role="alert">System status could not be loaded.</div>;
  }

  return (
    <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-6">
      <SystemStatusSummary status={status} />
      <SystemCheckList checks={status.checks} />
      <DemoDatasetSummary demo={status.demo} />
    </div>
  );
}
