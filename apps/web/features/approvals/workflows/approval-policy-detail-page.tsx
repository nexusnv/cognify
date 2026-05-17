"use client";

import { useQuery } from "@tanstack/react-query";
import { toast } from "sonner";
import { Button } from "@cognify/ui";
import { fetchApprovalPolicy } from "../api/approvals-api";
import { ApprovalPolicyPreview } from "../components/approval-policy-preview";
import { ApprovalStatusBadge } from "../components/approval-status-badge";
import { approvalPolicyKeys, usePublishApprovalPolicyVersion } from "../hooks/use-approval-policies";

export function ApprovalPolicyDetailPage({ policyId }: { policyId: string }) {
  const policyQuery = useQuery({
    queryKey: approvalPolicyKeys.detail(policyId),
    queryFn: () => fetchApprovalPolicy(policyId),
  });
  const publishMutation = usePublishApprovalPolicyVersion(policyId);
  const policy = policyQuery.data;
  const latestVersion = policy?.versions[0];

  if (policyQuery.isLoading) {
    return <div className="rounded-md border p-4 text-sm text-muted-foreground">Loading approval policy</div>;
  }

  if (policyQuery.isError || !policy || !latestVersion) {
    return (
      <div className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
        Approval policy could not be loaded.
      </div>
    );
  }

  return (
    <section className="space-y-5">
      <div className="flex flex-col gap-3 border-b pb-4 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-2xl font-semibold">{policy.name}</h1>
          <p className="mt-1 text-sm text-muted-foreground">{policy.description || "No description"}</p>
        </div>
        {latestVersion.status === "draft" ? (
          <Button
            type="button"
            disabled={publishMutation.isPending}
            onClick={async () => {
              await publishMutation.mutateAsync(latestVersion.id);
              toast.success("Approval policy version published");
            }}
          >
            Publish version
          </Button>
        ) : null}
      </div>

      <div className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Versions</h2>
        <div className="mt-3 space-y-2">
          {policy.versions.map((version) => (
            <div key={version.id} className="flex items-center justify-between rounded-md border p-3">
              <span className="text-sm font-medium">Version {version.versionNumber}</span>
              <ApprovalStatusBadge status={version.status} />
            </div>
          ))}
        </div>
      </div>

      <ApprovalPolicyPreview
        values={{
          name: policy.name,
          description: policy.description ?? "",
          subjectType: policy.subjectType,
          rules: latestVersion.rules,
          routeTemplate: latestVersion.routeTemplate,
          slaRules: latestVersion.slaRules,
        }}
      />
    </section>
  );
}
