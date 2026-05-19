"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useForm, useWatch } from "react-hook-form";
import { Button, NativeSelect, Textarea } from "@cognify/ui";
import { ApprovalPolicyPreview } from "../components/approval-policy-preview";
import {
  approvalPolicySchema,
  defaultApprovalPolicyValues,
  type ApprovalPolicySchemaInput,
  type ApprovalPolicySchemaValues,
} from "../schemas/approval-policy-schema";

export function ApprovalPolicyForm({
  defaultValues = defaultApprovalPolicyValues,
  submitLabel = "Save policy",
  onSubmit,
}: {
  defaultValues?: ApprovalPolicySchemaValues;
  submitLabel?: string;
  onSubmit: (values: ApprovalPolicySchemaValues) => Promise<void> | void;
}) {
  const {
    register,
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ApprovalPolicySchemaInput, unknown, ApprovalPolicySchemaValues>({
    resolver: zodResolver(approvalPolicySchema),
    defaultValues,
  });
  const values = useWatch({ control }) as ApprovalPolicySchemaValues;

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
      <div className="space-y-4">
        <div>
          <label htmlFor="approval-policy-name" className="block text-sm font-medium">
            Policy name
          </label>
          <input
            id="approval-policy-name"
            {...register("name")}
            className="mt-1 min-h-11 w-full rounded-md border px-3 text-base"
            aria-invalid={Boolean(errors.name)}
          />
          {errors.name ? <p className="mt-1 text-sm text-red-600">{errors.name.message}</p> : null}
        </div>

        <div>
          <label htmlFor="approval-policy-description" className="block text-sm font-medium">
            Description
          </label>
          <Textarea id="approval-policy-description" {...register("description")} className="mt-1" />
        </div>

        <label className="block space-y-1.5 text-sm font-medium">
          Subject type
          <NativeSelect {...register("subjectType")}>
            <option value="requisition">Requisition</option>
          </NativeSelect>
        </label>

        <div className="grid gap-3 rounded-md border p-3 md:grid-cols-3">
          <label className="space-y-1.5 text-sm font-medium">
            Stage
            <input
              {...register("routeTemplate.stages.0.name")}
              className="min-h-11 w-full rounded-md border px-3 text-base"
            />
          </label>
          <label className="space-y-1.5 text-sm font-medium">
            Completion
            <NativeSelect {...register("routeTemplate.stages.0.completionRule")}>
              <option value="all">All</option>
              <option value="any">Any</option>
            </NativeSelect>
          </label>
          <label className="space-y-1.5 text-sm font-medium">
            Due hours
            <input
              type="number"
              min={1}
              {...register("slaRules.0.dueInHours", { valueAsNumber: true })}
              className="min-h-11 w-full rounded-md border px-3 text-base"
            />
          </label>
        </div>

        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? "Saving..." : submitLabel}
        </Button>
      </div>

      <ApprovalPolicyPreview values={values ?? defaultValues} />
    </form>
  );
}
