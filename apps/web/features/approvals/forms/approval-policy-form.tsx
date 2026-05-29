"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { Plus, Trash2 } from "lucide-react";
import { useEffect } from "react";
import { useFieldArray, useForm, useWatch } from "react-hook-form";
import type { FieldErrors } from "react-hook-form";
import { Button, NativeSelect, Textarea } from "@cognify/ui";
import { ApprovalPolicyPreview } from "../components/approval-policy-preview";
import {
  awardRuleFields,
  approvalPolicySchema,
  defaultApprovalPolicyValues,
  requisitionRuleFields,
  type ApprovalPolicySchemaInput,
  type ApprovalPolicySchemaValues,
} from "../schemas/approval-policy-schema";
import type { ApprovalPolicyRule } from "../types/approval-view-model";

const subjectLabels = {
  requisition: "Requisition",
  rfq_award_recommendation: "RFQ award recommendation",
} as const;

const ruleFieldLabels: Record<string, string> = {
  amount: "Amount",
  department: "Department",
  costCenter: "Cost center",
  projectId: "Project",
  riskClassification: "Risk classification",
  recommendedAmount: "Recommended amount",
  recommendedCurrency: "Recommended currency",
  recommendedVendorId: "Recommended vendor",
  scorecardWeightedTotal: "Scorecard weighted total",
  riskSummaryPresent: "Risk summary present",
  exceptionSummaryPresent: "Exception summary present",
};

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
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<ApprovalPolicySchemaInput, unknown, ApprovalPolicySchemaValues>({
    resolver: zodResolver(approvalPolicySchema),
    defaultValues,
  });
  const values = useWatch({ control }) as ApprovalPolicySchemaValues;
  const subjectType = values?.subjectType ?? defaultValues.subjectType;
  const ruleFields =
    subjectType === "rfq_award_recommendation" ? awardRuleFields : requisitionRuleFields;
  const rules = useFieldArray({ control, name: "rules" });

  useEffect(() => {
    const firstStageName = values?.routeTemplate?.stages?.[0]?.name;
    if (firstStageName && values?.slaRules?.[0]?.stage !== firstStageName) {
      setValue("slaRules.0.stage", firstStageName, { shouldDirty: true });
    }
  }, [setValue, values?.routeTemplate?.stages, values?.slaRules]);

  useEffect(() => {
    const supportedFields = new Set<string>(ruleFields);
    const hasUnsupportedRule = values?.rules?.some((rule) => !supportedFields.has(rule.field));

    if (hasUnsupportedRule) {
      setValue("rules", [], { shouldDirty: true, shouldValidate: true });
    }
  }, [ruleFields, setValue, values?.rules]);

  const addRule = () => {
    rules.append({
      field: ruleFields[0],
      operator: "equals",
      value: defaultRuleValue(ruleFields[0]),
    });
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
      <div className="space-y-4">
        <div className="grid gap-3 rounded-md border p-3 md:grid-cols-[minmax(0,1fr)_14rem]">
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

          <label className="block space-y-1.5 text-sm font-medium">
            Subject type
            <NativeSelect {...register("subjectType")}>
              {Object.entries(subjectLabels).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </NativeSelect>
          </label>
        </div>

        <div>
          <label htmlFor="approval-policy-description" className="block text-sm font-medium">
            Description
          </label>
          <Textarea id="approval-policy-description" {...register("description")} className="mt-1" />
        </div>

        <section className="rounded-md border p-3" aria-labelledby="approval-policy-rules-title">
          <div className="flex items-center justify-between gap-3">
            <h2 id="approval-policy-rules-title" className="text-sm font-semibold">
              Rules
            </h2>
            <Button type="button" variant="outline" size="sm" onClick={addRule}>
              <Plus className="h-4 w-4" aria-hidden="true" />
              Add rule
            </Button>
          </div>

          <div className="mt-3 space-y-2">
            {rules.fields.length === 0 ? (
              <p className="text-sm text-muted-foreground">No rules. The policy can act as a fallback route.</p>
            ) : null}
            {rules.fields.map((rule, index) => (
              <div
                key={rule.id}
                className="grid gap-2 rounded-md border p-2 md:grid-cols-[minmax(9rem,1fr)_9rem_minmax(8rem,1fr)_2.75rem]"
              >
                <label className="space-y-1 text-xs font-medium">
                  Field
                  <NativeSelect
                    {...register(`rules.${index}.field`)}
                    aria-label={`Rule ${index + 1} field`}
                    onChange={(event) => {
                      setValue(`rules.${index}.field`, event.target.value, {
                        shouldDirty: true,
                        shouldValidate: true,
                      });
                      setValue(`rules.${index}.value`, defaultRuleValue(event.target.value), {
                        shouldDirty: true,
                        shouldValidate: true,
                      });
                    }}
                  >
                    {ruleFields.map((field) => (
                      <option key={field} value={field}>
                        {ruleFieldLabels[field]}
                      </option>
                    ))}
                  </NativeSelect>
                </label>

                <label className="space-y-1 text-xs font-medium">
                  Operator
                  <NativeSelect {...register(`rules.${index}.operator`)} aria-label={`Rule ${index + 1} operator`}>
                    <option value="equals">Equals</option>
                    <option value="in">In</option>
                    <option value="gte">Greater or equal</option>
                    <option value="lte">Less or equal</option>
                    <option value="between">Between</option>
                  </NativeSelect>
                </label>

                <label className="space-y-1 text-xs font-medium">
                  Value
                  <input
                    {...register(`rules.${index}.value`, { setValueAs: coerceRuleValue })}
                    aria-label={`Rule ${index + 1} value`}
                    className="min-h-11 w-full rounded-md border px-3 text-base"
                  />
                </label>

                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  aria-label={`Remove rule ${index + 1}`}
                  className="self-end"
                  onClick={() => rules.remove(index)}
                >
                  <Trash2 className="h-4 w-4" aria-hidden="true" />
                </Button>
                {errors.rules?.[index]?.field ? (
                  <p className="md:col-span-4 text-sm text-red-600">
                    {errors.rules[index]?.field?.message}
                  </p>
                ) : null}
              </div>
            ))}
          </div>
        </section>

        <div className="grid gap-3 rounded-md border p-3 md:grid-cols-3">
          <label className="space-y-1.5 text-sm font-medium">
            Stage name
            <input
              {...register("routeTemplate.stages.0.name")}
              aria-label="Stage name"
              className="min-h-11 w-full rounded-md border px-3 text-base"
            />
          </label>
          <label className="space-y-1.5 text-sm font-medium">
            Completion rule
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
          <label className="space-y-1.5 text-sm font-medium">
            Escalation hours
            <input
              type="number"
              min={1}
              {...register("slaRules.0.escalateAfterHours", { valueAsNumber: true })}
              className="min-h-11 w-full rounded-md border px-3 text-base"
            />
          </label>
        </div>

        <div className="grid gap-3 md:grid-cols-2">
          <ApproverEditor
            title="Approver"
            prefix="routeTemplate.stages.0.approvers.0"
            register={register}
            errors={errors}
          />
          <ApproverEditor
            title="Fallback approver"
            prefix="routeTemplate.stages.0.fallbackApprovers.0"
            register={register}
            errors={errors}
          />
        </div>

        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? "Saving..." : submitLabel}
        </Button>
      </div>

      <ApprovalPolicyPreview values={values ?? defaultValues} />
    </form>
  );
}

function ApproverEditor({
  title,
  prefix,
  register,
  errors,
}: {
  title: string;
  prefix:
    | "routeTemplate.stages.0.approvers.0"
    | "routeTemplate.stages.0.fallbackApprovers.0";
  register: ReturnType<
    typeof useForm<ApprovalPolicySchemaInput, unknown, ApprovalPolicySchemaValues>
  >["register"];
  errors: FieldErrors<ApprovalPolicySchemaInput>;
}) {
  const typeError = nestedErrorMessage(errors, `${prefix}.type`);
  const roleError = nestedErrorMessage(errors, `${prefix}.role`);
  const userIdError = nestedErrorMessage(errors, `${prefix}.userId`);

  return (
    <fieldset className="grid gap-2 rounded-md border p-3">
      <legend className="px-1 text-sm font-semibold">{title}</legend>
      <label className="space-y-1 text-xs font-medium">
        Type
        <NativeSelect {...register(`${prefix}.type`)} aria-label={`${title} type`} aria-invalid={Boolean(typeError)}>
          <option value="role">Role</option>
          <option value="user">User</option>
        </NativeSelect>
        {typeError ? <span className="block text-xs text-red-600">{typeError}</span> : null}
      </label>
      <label className="space-y-1 text-xs font-medium">
        Role
        <input
          {...register(`${prefix}.role`)}
          aria-label={`${title} role`}
          aria-invalid={Boolean(roleError)}
          className="min-h-11 w-full rounded-md border px-3 text-base"
        />
        {roleError ? <span className="block text-xs text-red-600">{roleError}</span> : null}
      </label>
      <label className="space-y-1 text-xs font-medium">
        User ID
        <input
          {...register(`${prefix}.userId`)}
          aria-label={`${title} user ID`}
          aria-invalid={Boolean(userIdError)}
          className="min-h-11 w-full rounded-md border px-3 text-base"
        />
        {userIdError ? <span className="block text-xs text-red-600">{userIdError}</span> : null}
      </label>
      <label className="space-y-1 text-xs font-medium">
        Label
        <input
          {...register(`${prefix}.label`)}
          aria-label={`${title} label`}
          className="min-h-11 w-full rounded-md border px-3 text-base"
        />
      </label>
    </fieldset>
  );
}

function nestedErrorMessage(errors: FieldErrors<ApprovalPolicySchemaInput>, path: string): string | undefined {
  const error = path
    .split(".")
    .reduce<unknown>((value, key) => (
      typeof value === "object" && value !== null ? (value as Record<string, unknown>)[key] : undefined
    ), errors);

  return typeof error === "object" &&
    error !== null &&
    "message" in error &&
    typeof (error as { message?: unknown }).message === "string"
    ? (error as { message: string }).message
    : undefined;
}

function defaultRuleValue(field: string): ApprovalPolicyRule["value"] {
  if (field === "riskSummaryPresent" || field === "exceptionSummaryPresent") return true;
  if (field === "amount" || field === "recommendedAmount" || field === "scorecardWeightedTotal") return 0;
  return "";
}

function coerceRuleValue(value: unknown): ApprovalPolicyRule["value"] {
  if (typeof value !== "string") return value as ApprovalPolicyRule["value"];
  const trimmed = value.trim();
  if (trimmed === "true") return true;
  if (trimmed === "false") return false;
  if (trimmed.includes(",")) return trimmed.split(",").map((item) => coerceRuleValue(item));
  if (trimmed !== "" && !Number.isNaN(Number(trimmed))) return Number(trimmed);
  return trimmed;
}
