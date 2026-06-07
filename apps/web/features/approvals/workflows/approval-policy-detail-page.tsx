"use client";

import { useQuery } from "@tanstack/react-query";
import { toast } from "sonner";
import {
  Alert,
  AlertDescription,
  AlertTitle,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@cognify/ui";
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
        <CardContent className="py-4 text-sm text-muted-foreground">
          Loading approval policy
        </CardContent>
      </Card>
    );
  }

  if (policyQuery.isError || !policy || !latestVersion) {
    return (
      <Alert variant="destructive">
        <AlertTitle>Approval policy unavailable</AlertTitle>
        <AlertDescription>Approval policy could not be loaded.</AlertDescription>
      </Alert>
    );
  }

  return (
    <section className="space-y-5">
      <Card className="py-0">
        <CardHeader className="flex flex-col gap-3 border-b bg-muted/30 md:flex-row md:items-start md:justify-between">
          <div className="space-y-1">
            <CardTitle>
              <h1 className="text-2xl font-semibold">{policy.name}</h1>
            </CardTitle>
            <CardDescription>{policy.description || "No description"}</CardDescription>
          </div>
          <div className="flex flex-wrap gap-2">
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
          </div>
        </CardHeader>
      </Card>

      <Card className="py-0">
        <CardHeader className="border-b bg-muted/30">
          <CardTitle>Versions</CardTitle>
          <CardDescription>
            Review the current draft and retire older route versions as needed.
          </CardDescription>
        </CardHeader>
        <CardContent className="p-0">
          <Table>
            <TableHeader className="bg-muted/40">
              <TableRow>
                <TableHead>Version</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {policy.versions.map((version) => (
                <TableRow key={version.id}>
                  <TableCell className="py-3 font-medium">
                    Version {version.versionNumber}
                  </TableCell>
                  <TableCell className="py-3">
                    <ApprovalStatusBadge status={version.status} />
                  </TableCell>
                  <TableCell className="py-3">
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
                    ) : (
                      <span className="text-sm text-muted-foreground">No actions</span>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
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
