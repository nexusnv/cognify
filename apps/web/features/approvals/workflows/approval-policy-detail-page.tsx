"use client";

import { useQuery } from "@tanstack/react-query";
import { toast } from "sonner";
import { Alert, AlertDescription, Button, Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
import { PageHeader } from "@/components/ui/page-header";
import { fetchApprovalPolicy } from "../api/approvals-api";
import { ApprovalPolicyPreview } from "../components/approval-policy-preview";
import { ApprovalStatusBadge } from "../components/approval-status-badge";
import {
  approvalPolicyKeys,
  useCreateApprovalPolicyVersion,
  usePublishApprovalPolicyVersion,
  useRetireApprovalPolicyVersion,
} from "../hooks/use-approval-policies";

export function ApprovalPolicyDetailPage({ policyId }: { policyId: string }) {
  const policyQuery = useQuery({
    queryKey: approvalPolicyKeys.detail(policyId),
    queryFn: () => fetchApprovalPolicy(policyId),
  });
  const publishMutation = usePublishApprovalPolicyVersion(policyId);
  const createVersionMutation = useCreateApprovalPolicyVersion(policyId);
  const retireMutation = useRetireApprovalPolicyVersion(policyId);
  const policy = policyQuery.data;
  const latestVersion = policy?.versions.reduce(
    (latest, version) => (version.versionNumber > latest.versionNumber ? version : latest),
    policy.versions[0],
  );

  if (policyQuery.isLoading) {
    return (
      <Card>
        <CardContent className="p-4 text-sm text-muted-foreground">Loading approval policy</CardContent>
      </Card>
    );
  }

  if (policyQuery.isError || !policy || !latestVersion) {
    return (
      <Alert variant="destructive">
        <AlertDescription>Approval policy could not be loaded.</AlertDescription>
      </Alert>
    );
  }

  return (
    <section className="space-y-5">
      <PageHeader
        title={policy.name}
        description={policy.description || "No description"}
        actions={
          <>
            <Button
              type="button"
              variant="outline"
              disabled={createVersionMutation.isPending}
              onClick={async () => {
                await createVersionMutation.mutateAsync({
                  subjectType: policy.subjectType,
                  priority: latestVersion.priority,
                  rules: latestVersion.rules,
                  routeTemplate: latestVersion.routeTemplate,
                  slaRules: latestVersion.slaRules,
                });
                toast.success("Approval policy version draft created");
              }}
            >
              New version draft
            </Button>
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
          </>
        }
      />

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Versions</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          {policy.versions.map((version) => (
            <Card key={version.id}>
              <CardContent className="flex items-center justify-between p-3">
                <span className="text-sm font-medium">Version {version.versionNumber}</span>
                <div className="flex items-center gap-2">
                  <ApprovalStatusBadge status={version.status} />
                  {version.status !== "retired" ? (
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      disabled={retireMutation.isPending}
                      aria-label={`Retire version ${version.versionNumber}`}
                      onClick={async () => {
                        await retireMutation.mutateAsync(version.id);
                        toast.success("Approval policy version retired");
                      }}
                    >
                      Retire
                    </Button>
                  ) : null}
                </div>
              </CardContent>
            </Card>
          ))}
        </CardContent>
      </Card>

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
