"use client";

import Link from "next/link";
import { Plus } from "lucide-react";
import { ApprovalStatusBadge } from "../components/approval-status-badge";
import { useApprovalPolicies } from "../hooks/use-approval-policies";

export function ApprovalPolicyListPage() {
  const policiesQuery = useApprovalPolicies();
  const policies = policiesQuery.data?.data ?? [];

  return (
    <section className="space-y-5">
      <div className="flex flex-col gap-3 border-b pb-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Approval policies</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Configure tenant approval routes for requisition governance.
          </p>
        </div>
        <Link
          href="/approval-policies/new"
          className="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background hover:bg-foreground/90"
        >
          <Plus className="h-4 w-4" aria-hidden="true" />
          New policy
        </Link>
      </div>

      {policiesQuery.isLoading ? (
        <div className="rounded-md border p-4 text-sm text-muted-foreground">Loading policies</div>
      ) : policiesQuery.isError ? (
        <div className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
          Approval policies could not be loaded.
        </div>
      ) : policies.length === 0 ? (
        <div className="rounded-md border p-4 text-sm text-muted-foreground">
          No approval policies configured.
        </div>
      ) : (
        <div className="overflow-hidden rounded-md border">
          <table className="w-full text-sm">
            <thead className="bg-muted/50 text-left">
              <tr>
                <th className="px-3 py-2 font-medium">Name</th>
                <th className="px-3 py-2 font-medium">Subject</th>
                <th className="px-3 py-2 font-medium">Status</th>
                <th className="px-3 py-2 font-medium">Versions</th>
              </tr>
            </thead>
            <tbody>
              {policies.map((policy) => (
                <tr key={policy.id} className="border-t">
                  <td className="px-3 py-3">
                    <Link href={`/approval-policies/${policy.id}`} className="font-medium underline-offset-4 hover:underline">
                      {policy.name}
                    </Link>
                  </td>
                  <td className="px-3 py-3">{policy.subjectType}</td>
                  <td className="px-3 py-3">
                    <ApprovalStatusBadge status={policy.status} />
                  </td>
                  <td className="px-3 py-3">{policy.versions.length}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
