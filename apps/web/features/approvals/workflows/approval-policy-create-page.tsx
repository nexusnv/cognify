"use client";

import { useRouter } from "next/navigation";
import { toast } from "sonner";
import { ApprovalPolicyForm } from "../forms/approval-policy-form";
import { useCreateApprovalPolicy } from "../hooks/use-approval-policies";

export function ApprovalPolicyCreatePage() {
  const router = useRouter();
  const createMutation = useCreateApprovalPolicy();

  return (
    <section className="space-y-5">
      <div className="border-b pb-4">
        <h1 className="text-2xl font-semibold">New approval policy</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Create a draft requisition approval route for this tenant.
        </p>
      </div>
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
