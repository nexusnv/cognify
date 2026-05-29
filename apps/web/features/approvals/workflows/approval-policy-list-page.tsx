"use client";

import Link from "next/link";
import { Plus } from "lucide-react";
import { Button, Card, CardContent, CardHeader, CardTitle, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@cognify/ui";
import { PageHeader } from "@/components/ui/page-header";
import { ApprovalStatusBadge } from "../components/approval-status-badge";
import { useApprovalPolicies } from "../hooks/use-approval-policies";

export function ApprovalPolicyListPage() {
  const policiesQuery = useApprovalPolicies();
  const policies = policiesQuery.data?.data ?? [];

  return (
    <section className="space-y-5">
      <PageHeader
        title="Approval policies"
        description="Configure tenant approval routes for requisition governance."
        actions={
          <Button asChild className="gap-2">
            <Link href="/approval-policies/new">
              <Plus className="h-4 w-4" aria-hidden="true" />
              New policy
            </Link>
          </Button>
        }
      />

      {policiesQuery.isLoading ? (
        <Card>
          <CardContent className="p-4 text-sm text-muted-foreground">Loading policies</CardContent>
        </Card>
      ) : policiesQuery.isError ? (
        <Card className="border-destructive/30 bg-destructive/5">
          <CardContent className="p-4 text-sm text-destructive">
            Approval policies could not be loaded.
          </CardContent>
        </Card>
      ) : policies.length === 0 ? (
        <Card>
          <CardContent className="p-4 text-sm text-muted-foreground">
            No approval policies configured.
          </CardContent>
        </Card>
      ) : (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Policy list</CardTitle>
          </CardHeader>
          <CardContent className="overflow-hidden p-0">
            <Table>
              <TableHeader className="bg-muted/50 text-left">
                <TableRow>
                  <TableHead className="px-3 py-2 font-medium">Name</TableHead>
                  <TableHead className="px-3 py-2 font-medium">Subject</TableHead>
                  <TableHead className="px-3 py-2 font-medium">Status</TableHead>
                  <TableHead className="px-3 py-2 font-medium">Versions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {policies.map((policy) => (
                  <TableRow key={policy.id}>
                    <TableCell className="px-3 py-3">
                      <Link href={`/approval-policies/${policy.id}`} className="font-medium underline-offset-4 hover:underline">
                        {policy.name}
                      </Link>
                    </TableCell>
                    <TableCell className="px-3 py-3">{policy.subjectType}</TableCell>
                    <TableCell className="px-3 py-3">
                      <ApprovalStatusBadge status={policy.status} />
                    </TableCell>
                    <TableCell className="px-3 py-3">{policy.versions.length}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}
    </section>
  );
}
