"use client";

import Link from "next/link";
import { Plus } from "lucide-react";
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
import { ApprovalStatusBadge } from "../components/approval-status-badge";
import { useApprovalPolicies } from "../hooks/use-approval-policies";

export function ApprovalPolicyListPage() {
  const policiesQuery = useApprovalPolicies();
  const policies = policiesQuery.data?.data ?? [];

  return (
    <section className="space-y-5">
      <Card className="py-0">
        <CardHeader className="flex flex-col gap-3 border-b bg-muted/30 md:flex-row md:items-center md:justify-between">
          <div className="space-y-1">
            <CardTitle>
              <h1 className="text-2xl font-semibold">Approval policies</h1>
            </CardTitle>
            <CardDescription>
              Configure tenant approval routes for requisition governance.
            </CardDescription>
          </div>
          <Button asChild className="shrink-0">
            <Link href="/approval-policies/new">
              <Plus className="h-4 w-4" aria-hidden="true" />
              New policy
            </Link>
          </Button>
        </CardHeader>
      </Card>

      {policiesQuery.isLoading ? (
        <Card>
          <CardContent className="py-4 text-sm text-muted-foreground">
            Loading policies
          </CardContent>
        </Card>
      ) : policiesQuery.isError ? (
        <Alert variant="destructive">
          <AlertTitle>Approval policies unavailable</AlertTitle>
          <AlertDescription>Approval policies could not be loaded.</AlertDescription>
        </Alert>
      ) : policies.length === 0 ? (
        <Card>
          <CardContent className="py-4 text-sm text-muted-foreground">
            No approval policies configured.
          </CardContent>
        </Card>
      ) : (
        <Card className="py-0">
          <CardContent className="p-0">
            <Table>
              <TableHeader className="bg-muted/40">
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Subject</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Versions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
              {policies.map((policy) => (
                <TableRow key={policy.id}>
                  <TableCell className="py-3">
                    <Link
                      href={`/approval-policies/${policy.id}`}
                      className="font-medium underline-offset-4 hover:underline"
                    >
                      {policy.name}
                    </Link>
                  </TableCell>
                  <TableCell className="py-3">{policy.subjectType}</TableCell>
                  <TableCell className="py-3">
                    <ApprovalStatusBadge status={policy.status} />
                  </TableCell>
                  <TableCell className="py-3">{policy.versions.length}</TableCell>
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
