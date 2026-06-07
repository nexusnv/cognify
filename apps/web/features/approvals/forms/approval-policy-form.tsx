"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { Plus, Trash2 } from "lucide-react";
import { useEffect } from "react";
import { Controller, useFieldArray, useForm, useWatch } from "react-hook-form";
import type { FieldErrors } from "react-hook-form";
import {
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Field,
  FieldContent,
  FieldDescription,
  FieldError,
  FieldLabel,
  Form,
  Input,
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
  Switch,
  Textarea,
} from "@cognify/ui";
import { ApprovalPolicyPreview } from "../components/approval-policy-preview";
import {
  approvalPolicySchema,
  awardRuleFields,
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

const operatorLabels = {
  equals: "Equals",
  in: "In",
  gte: "Greater or equal",
  lte: "Less or equal",
  between: "Between",
} as const;

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
    const initialField = ruleFields[0];
    rules.append({
      field: initialField,
      operator: "equals",
      value: defaultRuleValue(initialField),
    });
  };

  return (
    <Form
      onSubmit={handleSubmit(onSubmit)}
      className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]"
    >
      <div className="space-y-5">
        <Card className="py-0">
          <CardHeader className="border-b bg-muted/30">
            <CardTitle>Policy basics</CardTitle>
            <CardDescription>
              Set the policy scope and the subject type this route governs.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 py-4">
            <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_14rem]">
              <Field data-invalid={Boolean(errors.name)}>
                <FieldLabel htmlFor="approval-policy-name">Policy name</FieldLabel>
                <FieldContent>
                  <Input
                    id="approval-policy-name"
                    {...register("name")}
                    aria-invalid={Boolean(errors.name)}
                    aria-describedby={errors.name ? "approval-policy-name-error" : undefined}
                  />
                  <FieldError id="approval-policy-name-error" errors={[errors.name]} />
                </FieldContent>
              </Field>

              <Field data-invalid={Boolean(errors.subjectType)}>
                <FieldLabel htmlFor="approval-policy-subject-type">Subject type</FieldLabel>
                <FieldContent>
                  <Controller
                    control={control}
                    name="subjectType"
                    render={({ field }) => (
                      <Select value={field.value} onValueChange={field.onChange}>
                        <SelectTrigger
                          id="approval-policy-subject-type"
                          aria-label="Subject type"
                          onBlur={field.onBlur}
                        >
                          <SelectValue placeholder="Select a subject type" />
                        </SelectTrigger>
                        <SelectContent>
                          {Object.entries(subjectLabels).map(([value, label]) => (
                            <SelectItem key={value} value={value}>
                              {label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    )}
                  />
                </FieldContent>
              </Field>
            </div>

            <Field data-invalid={Boolean(errors.description)}>
              <FieldLabel htmlFor="approval-policy-description">Description</FieldLabel>
              <FieldContent>
                <Textarea
                  id="approval-policy-description"
                  {...register("description")}
                  aria-invalid={Boolean(errors.description)}
                  aria-describedby={
                    errors.description
                      ? "approval-policy-description-error"
                      : "approval-policy-description-description"
                  }
                />
                <FieldDescription id="approval-policy-description-description">
                  Describe the governance intent and any exception handling this route covers.
                </FieldDescription>
                <FieldError
                  id="approval-policy-description-error"
                  errors={[errors.description]}
                />
              </FieldContent>
            </Field>
          </CardContent>
        </Card>

        <Card className="py-0">
          <CardHeader className="border-b bg-muted/30">
            <div className="flex items-center justify-between gap-3">
              <div className="space-y-1">
                <CardTitle>Rules</CardTitle>
                <CardDescription>
                  Match requisitions or award recommendations to the right route.
                </CardDescription>
              </div>
              <Button type="button" variant="outline" size="sm" onClick={addRule}>
                <Plus className="h-4 w-4" aria-hidden="true" />
                Add rule
              </Button>
            </div>
          </CardHeader>
          <CardContent className="space-y-3 py-4">
            {rules.fields.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                No rules. The policy can act as a fallback route.
              </p>
            ) : null}

            {rules.fields.map((rule, index) => {
              const selectedField = values?.rules?.[index]?.field ?? rule.field;
              const fieldError = errors.rules?.[index]?.field;
              const operatorError = errors.rules?.[index]?.operator;
              const valueError = errors.rules?.[index]?.value;
              const ruleValueKind = valueKindForField(selectedField);

              return (
                <div
                  key={rule.id}
                  className="grid gap-4 rounded-lg border bg-muted/20 p-4 md:grid-cols-[minmax(9rem,1fr)_9rem_minmax(10rem,1fr)_2.75rem]"
                >
                  <Field data-invalid={Boolean(fieldError)}>
                    <FieldLabel htmlFor={`approval-policy-rule-field-${index}`}>Field</FieldLabel>
                    <FieldContent>
                      <Controller
                        control={control}
                        name={`rules.${index}.field`}
                        render={({ field }) => (
                          <Select
                            value={field.value}
                            onValueChange={(nextValue) => {
                              field.onChange(nextValue);
                              setValue(`rules.${index}.value`, defaultRuleValue(nextValue), {
                                shouldDirty: true,
                                shouldValidate: true,
                              });
                            }}
                          >
                            <SelectTrigger
                              id={`approval-policy-rule-field-${index}`}
                              aria-label={`Rule ${index + 1} field`}
                              onBlur={field.onBlur}
                            >
                              <SelectValue placeholder="Select a field" />
                            </SelectTrigger>
                            <SelectContent>
                              {ruleFields.map((fieldName) => (
                                <SelectItem key={fieldName} value={fieldName}>
                                  {ruleFieldLabels[fieldName]}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        )}
                      />
                      <FieldError errors={[fieldError]} />
                    </FieldContent>
                  </Field>

                  <Field data-invalid={Boolean(operatorError)}>
                    <FieldLabel htmlFor={`approval-policy-rule-operator-${index}`}>
                      Operator
                    </FieldLabel>
                    <FieldContent>
                      <Controller
                        control={control}
                        name={`rules.${index}.operator`}
                        render={({ field }) => (
                          <Select value={field.value} onValueChange={field.onChange}>
                            <SelectTrigger
                              id={`approval-policy-rule-operator-${index}`}
                              aria-label={`Rule ${index + 1} operator`}
                              onBlur={field.onBlur}
                            >
                              <SelectValue placeholder="Select an operator" />
                            </SelectTrigger>
                            <SelectContent>
                              {Object.entries(operatorLabels).map(([value, label]) => (
                                <SelectItem key={value} value={value}>
                                  {label}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        )}
                      />
                      <FieldError errors={[operatorError]} />
                    </FieldContent>
                  </Field>

                  {ruleValueKind === "boolean" ? (
                    <Field data-invalid={Boolean(valueError)} orientation="horizontal">
                      <FieldLabel htmlFor={`approval-policy-rule-value-${index}`}>
                        Rule {index + 1} value
                      </FieldLabel>
                      <FieldContent className="items-start gap-2">
                        <Controller
                          control={control}
                          name={`rules.${index}.value`}
                          render={({ field }) => (
                            <div className="flex items-center gap-3 rounded-md border px-3 py-2">
                              <Switch
                                id={`approval-policy-rule-value-${index}`}
                                aria-label={`Rule ${index + 1} value`}
                                checked={Boolean(field.value)}
                                onCheckedChange={(checked) => field.onChange(Boolean(checked))}
                              />
                              <span className="text-sm text-muted-foreground">
                                {field.value ? "Enabled" : "Disabled"}
                              </span>
                            </div>
                          )}
                        />
                        <FieldDescription>
                          Toggle whether this condition must be true for the rule to match.
                        </FieldDescription>
                        <FieldError errors={[valueError]} />
                      </FieldContent>
                    </Field>
                  ) : (
                    <Field data-invalid={Boolean(valueError)}>
                      <FieldLabel htmlFor={`approval-policy-rule-value-${index}`}>Value</FieldLabel>
                      <FieldContent>
                        <Input
                          id={`approval-policy-rule-value-${index}`}
                          {...register(`rules.${index}.value`, {
                            setValueAs: coerceRuleValue,
                          })}
                          type={ruleValueKind === "number" ? "number" : "text"}
                          step={ruleValueKind === "number" ? "any" : undefined}
                          aria-label={`Rule ${index + 1} value`}
                          aria-invalid={Boolean(valueError)}
                          aria-describedby={
                            valueError
                              ? `approval-policy-rule-value-error-${index}`
                              : `approval-policy-rule-value-description-${index}`
                          }
                        />
                        <FieldDescription
                          id={`approval-policy-rule-value-description-${index}`}
                        >
                          {ruleValueKind === "list"
                            ? "Use comma-separated values for multi-match conditions."
                            : "Enter the value that should trigger this route."}
                        </FieldDescription>
                        <FieldError
                          id={`approval-policy-rule-value-error-${index}`}
                          errors={[valueError]}
                        />
                      </FieldContent>
                    </Field>
                  )}

                  <div className="flex items-end">
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      aria-label={`Remove rule ${index + 1}`}
                      onClick={() => rules.remove(index)}
                    >
                      <Trash2 className="h-4 w-4" aria-hidden="true" />
                    </Button>
                  </div>
                </div>
              );
            })}
          </CardContent>
        </Card>

        <Card className="py-0">
          <CardHeader className="border-b bg-muted/30">
            <CardTitle>Route template</CardTitle>
            <CardDescription>
              Define the active stage and service-level expectations for this policy.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 py-4 md:grid-cols-2">
            <Field data-invalid={Boolean(nestedErrorMessage(errors, "routeTemplate.stages.0.name"))}>
              <FieldLabel htmlFor="approval-policy-stage-name">Stage name</FieldLabel>
              <FieldContent>
                <Input
                  id="approval-policy-stage-name"
                  {...register("routeTemplate.stages.0.name")}
                  aria-label="Stage name"
                  aria-invalid={Boolean(nestedErrorMessage(errors, "routeTemplate.stages.0.name"))}
                />
                <FieldError
                  errors={[
                    nestedErrorForPath(errors, "routeTemplate.stages.0.name"),
                  ]}
                />
              </FieldContent>
            </Field>

            <Field
              data-invalid={Boolean(
                nestedErrorMessage(errors, "routeTemplate.stages.0.completionRule"),
              )}
            >
              <FieldLabel htmlFor="approval-policy-completion-rule">
                Completion rule
              </FieldLabel>
              <FieldContent>
                <Controller
                  control={control}
                  name="routeTemplate.stages.0.completionRule"
                  render={({ field }) => (
                    <Select value={field.value} onValueChange={field.onChange}>
                      <SelectTrigger
                        id="approval-policy-completion-rule"
                        aria-label="Completion rule"
                        onBlur={field.onBlur}
                      >
                        <SelectValue placeholder="Select a completion rule" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="all">All</SelectItem>
                        <SelectItem value="any">Any</SelectItem>
                      </SelectContent>
                    </Select>
                  )}
                />
                <FieldError
                  errors={[
                    nestedErrorForPath(errors, "routeTemplate.stages.0.completionRule"),
                  ]}
                />
              </FieldContent>
            </Field>

            <Field data-invalid={Boolean(nestedErrorMessage(errors, "slaRules.0.dueInHours"))}>
              <FieldLabel htmlFor="approval-policy-due-hours">Due hours</FieldLabel>
              <FieldContent>
                <Input
                  id="approval-policy-due-hours"
                  type="number"
                  min={1}
                  {...register("slaRules.0.dueInHours", { valueAsNumber: true })}
                  aria-label="Due hours"
                  aria-invalid={Boolean(nestedErrorMessage(errors, "slaRules.0.dueInHours"))}
                />
                <FieldError
                  errors={[nestedErrorForPath(errors, "slaRules.0.dueInHours")]}
                />
              </FieldContent>
            </Field>

            <Field
              data-invalid={Boolean(nestedErrorMessage(errors, "slaRules.0.escalateAfterHours"))}
            >
              <FieldLabel htmlFor="approval-policy-escalation-hours">
                Escalation hours
              </FieldLabel>
              <FieldContent>
                <Input
                  id="approval-policy-escalation-hours"
                  type="number"
                  min={1}
                  {...register("slaRules.0.escalateAfterHours", { valueAsNumber: true })}
                  aria-label="Escalation hours"
                  aria-invalid={Boolean(
                    nestedErrorMessage(errors, "slaRules.0.escalateAfterHours"),
                  )}
                />
                <FieldDescription>
                  Escalation follows the stage name automatically as you rename it.
                </FieldDescription>
                <FieldError
                  errors={[nestedErrorForPath(errors, "slaRules.0.escalateAfterHours")]}
                />
              </FieldContent>
            </Field>
          </CardContent>
        </Card>

        <div className="grid gap-4 md:grid-cols-2">
          <ApproverEditor
            title="Approver"
            prefix="routeTemplate.stages.0.approvers.0"
            control={control}
            register={register}
            errors={errors}
          />
          <ApproverEditor
            title="Fallback approver"
            prefix="routeTemplate.stages.0.fallbackApprovers.0"
            control={control}
            register={register}
            errors={errors}
          />
        </div>

        <div className="flex justify-end">
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? "Saving..." : submitLabel}
          </Button>
        </div>
      </div>

      <div className="lg:sticky lg:top-6 lg:self-start">
        <ApprovalPolicyPreview values={values ?? defaultValues} />
      </div>
    </Form>
  );
}

function ApproverEditor({
  title,
  prefix,
  control,
  register,
  errors,
}: {
  title: string;
  prefix:
    | "routeTemplate.stages.0.approvers.0"
    | "routeTemplate.stages.0.fallbackApprovers.0";
  control: ReturnType<
    typeof useForm<ApprovalPolicySchemaInput, unknown, ApprovalPolicySchemaValues>
  >["control"];
  register: ReturnType<
    typeof useForm<ApprovalPolicySchemaInput, unknown, ApprovalPolicySchemaValues>
  >["register"];
  errors: FieldErrors<ApprovalPolicySchemaInput>;
}) {
  const typeError = nestedErrorForPath(errors, `${prefix}.type`);
  const roleError = nestedErrorForPath(errors, `${prefix}.role`);
  const userIdError = nestedErrorForPath(errors, `${prefix}.userId`);
  const labelError = nestedErrorForPath(errors, `${prefix}.label`);

  return (
    <Card className="py-0">
      <CardHeader className="border-b bg-muted/30">
        <CardTitle>{title}</CardTitle>
        <CardDescription>
          Configure who acts on this stage and how the UI labels that approver.
        </CardDescription>
      </CardHeader>
      <CardContent className="grid gap-4 py-4">
        <Field data-invalid={Boolean(typeError)}>
          <FieldLabel htmlFor={`${prefix}-type`}>Type</FieldLabel>
          <FieldContent>
            <Controller
              control={control}
              name={`${prefix}.type`}
              render={({ field }) => (
                <Select value={field.value} onValueChange={field.onChange}>
                  <SelectTrigger
                    id={`${prefix}-type`}
                    aria-label={`${title} type`}
                    onBlur={field.onBlur}
                  >
                    <SelectValue placeholder="Select an approver type" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="role">Role</SelectItem>
                    <SelectItem value="user">User</SelectItem>
                  </SelectContent>
                </Select>
              )}
            />
            <FieldError errors={[typeError]} />
          </FieldContent>
        </Field>

        <Field data-invalid={Boolean(roleError)}>
          <FieldLabel htmlFor={`${prefix}-role`}>Role</FieldLabel>
          <FieldContent>
            <Input
              id={`${prefix}-role`}
              {...register(`${prefix}.role`)}
              aria-label={`${title} role`}
              aria-invalid={Boolean(roleError)}
            />
            <FieldError errors={[roleError]} />
          </FieldContent>
        </Field>

        <Field data-invalid={Boolean(userIdError)}>
          <FieldLabel htmlFor={`${prefix}-user-id`}>User ID</FieldLabel>
          <FieldContent>
            <Input
              id={`${prefix}-user-id`}
              {...register(`${prefix}.userId`)}
              aria-label={`${title} user ID`}
              aria-invalid={Boolean(userIdError)}
            />
            <FieldError errors={[userIdError]} />
          </FieldContent>
        </Field>

        <Field data-invalid={Boolean(labelError)}>
          <FieldLabel htmlFor={`${prefix}-label`}>Label</FieldLabel>
          <FieldContent>
            <Input
              id={`${prefix}-label`}
              {...register(`${prefix}.label`)}
              aria-label={`${title} label`}
              aria-invalid={Boolean(labelError)}
            />
            <FieldDescription>
              Optional display name used in route previews and live approval tasks.
            </FieldDescription>
            <FieldError errors={[labelError]} />
          </FieldContent>
        </Field>
      </CardContent>
    </Card>
  );
}

function nestedErrorForPath(errors: FieldErrors<ApprovalPolicySchemaInput>, path: string) {
  return path
    .split(".")
    .reduce<unknown>(
      (value, key) =>
        typeof value === "object" && value !== null
          ? (value as Record<string, unknown>)[key]
          : undefined,
      errors,
    ) as { message?: string } | undefined;
}

function nestedErrorMessage(errors: FieldErrors<ApprovalPolicySchemaInput>, path: string): string | undefined {
  return nestedErrorForPath(errors, path)?.message;
}

function defaultRuleValue(field: string): ApprovalPolicyRule["value"] {
  if (field === "riskSummaryPresent" || field === "exceptionSummaryPresent") return true;
  if (field === "amount" || field === "recommendedAmount" || field === "scorecardWeightedTotal") return 0;
  return "";
}

function valueKindForField(field: string): "boolean" | "number" | "list" | "text" {
  if (field === "riskSummaryPresent" || field === "exceptionSummaryPresent") return "boolean";
  if (field === "amount" || field === "recommendedAmount" || field === "scorecardWeightedTotal") return "number";
  if (field === "department" || field === "costCenter" || field === "projectId" || field === "recommendedVendorId") {
    return "list";
  }
  return "text";
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
