"use client";

import { useRouter } from "next/navigation";
import { toast } from "sonner";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@cognify/ui";
import { ApprovalPolicyForm } from "../forms/approval-policy-form";
import { useCreateApprovalPolicy } from "../hooks/use-approval-policies";

export function ApprovalPolicyCreatePage() {
  const router = useRouter();
  const createMutation = useCreateApprovalPolicy();

  return (
    <section className="space-y-5">
      <Card className="py-0">
        <CardHeader className="border-b bg-muted/30">
          <CardTitle>
            <h1 className="text-2xl font-semibold">New approval policy</h1>
          </CardTitle>
          <CardDescription>
            Create a draft requisition approval route for this tenant.
          </CardDescription>
        </CardHeader>
        <CardContent className="py-4">
          <p className="text-sm text-muted-foreground">
            Configure matching rules, route stages, and SLA expectations before publishing.
          </p>
        </CardContent>
      </Card>
      <ApprovalPolicyForm
        submitLabel="Create policy"
        onSubmit={async (values) => {
          const policy = await createMutation.mutateAsync(values);
          toast.success("Approval policy draft created");
          router.push(`/approval-policies/${policy.id}`);
        }}
      />
    </section>
  );
}
