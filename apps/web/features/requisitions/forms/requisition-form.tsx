"use client";

import { Plus, Trash2 } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { Button, Card, CardContent, CardHeader, CardTitle, Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, Input, NativeSelect, Textarea } from "@cognify/ui";
import { getApiValidationErrors } from "@cognify/api-client";
import { FormErrorSummary } from "@/components/forms/form-error-summary";
import { FormField } from "@/components/forms/form-field";
import { useProjects } from "@/features/projects/hooks/use-projects";
import {
  flattenZodFieldErrors,
  focusFirstInvalidField,
  withFieldIds,
} from "@/components/forms/validation-errors";
import { SubmitRequisitionDialog } from "../components/submit-requisition-dialog";
import { SubmissionChecklist } from "../components/submission-checklist";
import { RequisitionLineItemSuggestionCombobox } from "../components/requisition-line-item-suggestion-combobox";
import { RequisitionSaveConflictPanel } from "../components/requisition-save-conflict-panel";
import { RequisitionTemplatePicker } from "../components/requisition-template-picker";
import { createRequisitionDraft, updateRequisitionDraft } from "../api/requisitions-api";
import { useApplyRequisitionTemplate, useRequisitionTemplates } from "../hooks/use-requisition-templates";
import { useRequisitionDraftSaveController } from "../hooks/use-requisition-draft-save-controller";
import { useRequisitionIntakeOptions } from "../hooks/use-requisition-intake-options";
import { useSubmitRequisition } from "../hooks/use-submit-requisition";
import { useUnsavedChangesGuard } from "../hooks/use-unsaved-changes-guard";
import { requisitionSubmitSchema } from "../schemas/requisition-form-schema";
import type {
  Requisition,
  RequisitionFormValues,
  RequisitionTemplate,
  RequisitionTemplateMode,
} from "../types/requisition-view-model";

const emptyLineItem = {
  name: "",
  description: "",
  quantity: 1,
  unit: "each",
  estimatedUnitPrice: 0,
  currency: "MYR",
};

const requisitionFieldIds: Record<string, string> = {
  title: "title",
  businessJustification: "business-justification",
  neededByDate: "needed-by",
  currency: "currency",
  department: "department",
  projectId: "project-id",
  costCenter: "cost-center",
  deliveryLocation: "delivery-location",
  lineItems: "line-items",
};

type PendingTemplate = {
  template: RequisitionTemplate;
  mode: RequisitionTemplateMode;
};

export function RequisitionForm({ initialRequisition }: { initialRequisition?: Requisition }) {
  const [values, setValues] = useState<RequisitionFormValues>({
    title: initialRequisition?.title ?? "",
    businessJustification: initialRequisition?.businessJustification ?? "",
    neededByDate: initialRequisition?.neededByDate ?? "",
    department: initialRequisition?.department ?? "",
    projectId: initialRequisition?.projectId ?? "",
    costCenter: initialRequisition?.costCenter ?? "",
    deliveryLocation: initialRequisition?.deliveryLocation ?? "",
    currency: initialRequisition?.currency ?? "MYR",
    lineItems: initialRequisition?.lineItems.length
      ? initialRequisition.lineItems
      : [{ ...emptyLineItem }],
  });
  const [status, setStatus] = useState(initialRequisition?.status ?? "draft");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [submittedNotice, setSubmittedNotice] = useState(false);
  const [pendingTemplate, setPendingTemplate] = useState<PendingTemplate | null>(null);
  const formRef = useRef<HTMLFormElement>(null);

  const saveController = useRequisitionDraftSaveController({
    initialRequisition: initialRequisition
      ? { id: initialRequisition.id, lockVersion: initialRequisition.lockVersion }
      : undefined,
    createDraft: async (draftValues) => {
      const requisition = await createRequisitionDraft(draftValues);
      return { id: requisition.id, lockVersion: requisition.lockVersion };
    },
    updateDraft: async (requisitionId, draftValues, lockVersion) => {
      const requisition = await updateRequisitionDraft(requisitionId, draftValues, lockVersion);
      return { id: requisition.id, lockVersion: requisition.lockVersion };
    },
  });
  const submitDraft = useSubmitRequisition();
  const templatesQuery = useRequisitionTemplates();
  const intakeOptionsQuery = useRequisitionIntakeOptions();
  const projectsQuery = useProjects({ status: "active", perPage: 100 });
  const applyTemplateMutation = useApplyRequisitionTemplate();
  const selectableProjects = useMemo(() => {
    const rows = projectsQuery.data?.data ?? [];
    const linkedProject = initialRequisition?.projectSummary;
    if (!linkedProject) return rows;

    const exists = rows.some((project) => project.id === linkedProject.id);
    if (exists) return rows;

    return [
      ...rows,
      {
        id: linkedProject.id,
        tenantId: initialRequisition?.tenantId ?? "",
        number: linkedProject.number,
        name: linkedProject.name,
        charter: "",
        status: linkedProject.status,
        owner: linkedProject.owner ?? { id: "", name: "Unknown owner", email: "" },
        budgetAmount: null,
        currency: "MYR",
        summary: {
          estimatedRequisitionTotal: 0,
          linkedRequisitionCount: 0,
          draftRequisitionCount: 0,
          submittedRequisitionCount: 0,
          changesRequestedRequisitionCount: 0,
          stoppedRequisitionCount: 0,
          approvalPlaceholderCount: 0,
          awardPlaceholderCount: 0,
        },
        permissions: {
          canUpdate: false,
          canActivate: false,
          canHold: false,
          canResume: false,
          canComplete: false,
          canCancel: false,
          canLinkRequisitions: false,
          canUnlinkRequisitions: false,
          canViewActivity: false,
        },
        createdAt: initialRequisition?.createdAt ?? "",
        updatedAt: initialRequisition?.updatedAt ?? "",
      },
    ];
  }, [initialRequisition, projectsQuery.data?.data]);

  const canEdit = initialRequisition?.permissions.canUpdate ?? status === "draft";
  const canSubmit = status === "draft" && (initialRequisition?.permissions.canSubmit ?? true);
  const saveLabel = status === "draft" ? "Save draft" : "Save changes";
  const errorSummary = useMemo(
    () => withFieldIds(flattenZodFieldErrors(collapsedFieldErrors(errors)), requisitionFieldIds),
    [errors],
  );
  const hasLineItemErrors = Object.keys(errors).some((key) => key.startsWith("lineItems."));
  const lineItemErrorMessage = firstFieldError(errors, "lineItems");
  const lineItemsErrorId = hasLineItemErrors ? "line-items-error" : undefined;
  const draftHasContent = hasMeaningfulDraftContent(values);
  const shouldWarnOnLeave =
    canEdit &&
    (saveController.saveState === "unsaved" ||
      saveController.saveState === "saving" ||
      saveController.saveState === "failed" ||
      saveController.saveState === "conflict");

  useUnsavedChangesGuard(shouldWarnOnLeave);

  useEffect(() => {
    if (saveController.saveState === "saved") {
      queueMicrotask(() => setErrors({}));
      return;
    }

    if (saveController.saveState !== "failed" && saveController.saveState !== "conflict") return;

    const validationErrors = getApiValidationErrors(saveController.lastError);

    if (Object.keys(validationErrors).length > 0) {
      queueMicrotask(() => setErrors(validationErrors));
      window.setTimeout(() => focusFirstInvalidField(formRef.current ?? document), 0);
      return;
    }

    if (saveController.saveState === "conflict") {
      queueMicrotask(() => setErrors({}));
    }
  }, [saveController.lastError, saveController.saveState]);

  function updateDraftValues(updater: (current: RequisitionFormValues) => RequisitionFormValues) {
    setValues((current) => {
      const next = updater(current);
      if (canEdit) {
        saveController.scheduleAutosave(next);
      }
      return next;
    });
  }

  function updateValue(field: keyof RequisitionFormValues, value: string) {
    setErrors((current) => clearFieldErrors(current, [String(field)]));
    updateDraftValues((current) => ({ ...current, [field]: value }));
  }

  function updateLineItem(
    index: number,
    field: keyof RequisitionFormValues["lineItems"][number],
    value: string,
  ) {
    setErrors((current) =>
      clearFieldErrors(current, [`lineItems.${index}.name`, `lineItems.${index}.quantity`, `lineItems.${index}.unit`, `lineItems.${index}.estimatedUnitPrice`, `lineItems.${index}.currency`, "lineItems"]),
    );
    updateDraftValues((current) => ({
      ...current,
      lineItems: current.lineItems.map((item, itemIndex) =>
        itemIndex === index
          ? {
              ...item,
              [field]:
                field === "quantity" || field === "estimatedUnitPrice" ? Number(value) : value,
            }
          : item,
      ),
    }));
  }

  function addLineItem() {
    updateDraftValues((current) => ({
      ...current,
      lineItems: [...current.lineItems, { ...emptyLineItem }],
    }));
  }

  function removeLineItem(index: number) {
    updateDraftValues((current) => ({
      ...current,
      lineItems: current.lineItems.filter((_, itemIndex) => itemIndex !== index),
    }));
  }

  async function handleSaveDraft() {
    if (!values.title.trim()) {
      setErrors({ title: ["Title is required."] });
      window.setTimeout(() => focusFirstInvalidField(formRef.current ?? document), 0);
      return undefined;
    }

    setErrors((current) => clearFieldErrors(current, ["title"]));

    const requisition = await saveController.saveNow(values);
    return requisition;
  }

  async function handleSubmitAttempt() {
    const result = requisitionSubmitSchema.safeParse(values);

    if (!result.success) {
      setErrors(result.error.flatten().fieldErrors);
      window.setTimeout(() => focusFirstInvalidField(formRef.current ?? document), 0);
      return;
    }

    setErrors({});
    setConfirmOpen(true);
  }

  async function handleConfirmSubmit() {
    try {
      const savedDraft = saveController.requisitionId ? undefined : await handleSaveDraft();
      const requisitionId = saveController.requisitionId ?? savedDraft?.id;
      if (!requisitionId) return;

      const response = await submitDraft.mutateAsync(requisitionId);
      setStatus(response.data.status);
      setConfirmOpen(false);
      setSubmittedNotice(true);
      toast.success("Requisition submitted");
    } catch (error) {
      const message = error instanceof Error ? error.message : "Unable to submit requisition";
      toast.error(message);
    }
  }

  async function handleApplyTemplate(template: RequisitionTemplate, mode: RequisitionTemplateMode) {
    if (draftHasContent) {
      setPendingTemplate({ template, mode });
      return;
    }

    const nextValues = mergeTemplateDefaults(values, template.defaults, mode);

    if (!saveController.requisitionId) {
      setValues(nextValues);
      saveController.scheduleAutosave(nextValues);
      return;
    }

    await applyTemplateNow(template, mode);
  }

  async function applyTemplateNow(template: RequisitionTemplate, mode: RequisitionTemplateMode) {
    if (!saveController.requisitionId) {
      const nextValues = mergeTemplateDefaults(values, template.defaults, mode);
      setValues(nextValues);
      saveController.scheduleAutosave(nextValues);
      setPendingTemplate(null);
      return;
    }

    try {
      const requisition = await applyTemplateMutation.mutateAsync({
        requisitionId: saveController.requisitionId,
        templateId: template.id,
        mode,
        lockVersion: saveController.lockVersion,
      });

      setValues(requisitionToFormValues(requisition));
      saveController.syncSavedDraft({
        id: requisition.id,
        lockVersion: requisition.lockVersion,
      });
      setStatus(requisition.status);
      setPendingTemplate(null);
    } catch (error) {
      setPendingTemplate(null);
      console.error("Unable to apply requisition template", error);
      toast.error("Unable to apply template. Refresh the draft and try again.");
    }
  }

  function renderDepartmentField() {
    const departments = intakeOptionsQuery.data?.departments ?? [];

    if (departments.length === 0) {
      return (
        <FormField htmlFor="department" label="Department" error={errors.department?.[0]}>
          <Input
            id="department"
            value={values.department}
            aria-invalid={Boolean(errors.department)}
            disabled={!canEdit}
            onChange={(event) => {
              if (canEdit) updateValue("department", event.target.value);
            }}
          />
        </FormField>
      );
    }

    const options = uniqueTextOptions(departments.map((department) => department.name), values.department);

    return (
      <FormField htmlFor="department" label="Department" error={errors.department?.[0]}>
        <NativeSelect
          id="department"
          value={values.department}
          aria-invalid={Boolean(errors.department)}
          disabled={!canEdit}
          onChange={(event) => {
            if (canEdit) updateValue("department", event.target.value);
          }}
        >
          <option value="">Select department</option>
          {options.map((department) => (
            <option key={department} value={department}>
              {department}
            </option>
          ))}
        </NativeSelect>
      </FormField>
    );
  }

  function renderCostCenterField() {
    const costCenters = intakeOptionsQuery.data?.costCenters ?? [];

    if (costCenters.length === 0) {
      return (
        <FormField htmlFor="cost-center" label="Cost center" error={errors.costCenter?.[0]}>
          <Input
            id="cost-center"
            value={values.costCenter}
            aria-invalid={Boolean(errors.costCenter)}
            disabled={!canEdit}
            onChange={(event) => {
              if (canEdit) updateValue("costCenter", event.target.value);
            }}
          />
        </FormField>
      );
    }

    const options = uniqueTextOptions(
      costCenters.map((costCenter) => `${costCenter.code} - ${costCenter.name}`),
      values.costCenter
        ? costCenters.some((item) => item.code === values.costCenter)
          ? `${values.costCenter} - ${costCenters.find((item) => item.code === values.costCenter)?.name ?? values.costCenter}`
          : values.costCenter
        : "",
    );

    return (
      <FormField htmlFor="cost-center" label="Cost center" error={errors.costCenter?.[0]}>
        <NativeSelect
          id="cost-center"
          value={
            values.costCenter
              ? costCenters.some((item) => item.code === values.costCenter)
                ? `${values.costCenter} - ${costCenters.find((item) => item.code === values.costCenter)?.name ?? values.costCenter}`
                : values.costCenter
              : ""
          }
          aria-invalid={Boolean(errors.costCenter)}
          disabled={!canEdit}
          onChange={(event) => {
            if (!canEdit) return;
            const selected = event.target.value;
            const match = costCenters.find((item) => `${item.code} - ${item.name}` === selected);
            updateValue("costCenter", match?.code ?? selected);
          }}
        >
          <option value="">Select cost center</option>
          {options.map((costCenter) => (
            <option key={costCenter} value={costCenter}>
              {costCenter}
            </option>
          ))}
        </NativeSelect>
      </FormField>
    );
  }

  return (
    <form ref={formRef} className="space-y-5" onSubmit={(event) => event.preventDefault()}>
      <div className="flex flex-col gap-3 border-b pb-4 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-2xl font-semibold">New requisition</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Create a draft, refine it with templates and suggestions, then submit for review.
          </p>
          <p className="mt-2 text-sm" aria-live="polite">
            {submittedNotice ? "Requisition submitted" : saveStateLabel(saveController.saveState)}
          </p>
          <p className="mt-1 text-sm font-medium">
            {requisitionStatusLabel(status)}
          </p>
        </div>
        <div className="flex flex-col gap-2 sm:flex-row">
          <Button type="button" variant="outline" onClick={handleSaveDraft} disabled={!canEdit || saveController.saveState === "saving"}>
            {saveLabel}
          </Button>
          {canSubmit ? (
            <Button type="button" onClick={handleSubmitAttempt} disabled={submitDraft.isPending}>
              Submit
            </Button>
          ) : null}
        </div>
      </div>

      <FormErrorSummary
        title="Complete the highlighted fields before submitting."
        errors={errorSummary}
      />

      {saveController.saveState === "conflict" ? (
        <RequisitionSaveConflictPanel onReload={() => window.location.reload()} />
      ) : null}

      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div className="space-y-5">
          <Card>
            <CardHeader>
              <CardTitle>Request summary</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
            <FormField htmlFor="title" label="Title" error={errors.title?.[0]} required>
              <Input
                id="title"
                value={values.title}
                aria-invalid={Boolean(errors.title)}
                onChange={(event) => updateValue("title", event.target.value)}
                disabled={!canEdit}
              />
            </FormField>
            <FormField
              htmlFor="needed-by"
              label="Needed by"
              error={errors.neededByDate?.[0]}
              required
            >
              <Input
                id="needed-by"
                type="date"
                value={values.neededByDate}
                aria-invalid={Boolean(errors.neededByDate)}
                onChange={(event) => updateValue("neededByDate", event.target.value)}
                disabled={!canEdit}
              />
            </FormField>
            <FormField
              htmlFor="business-justification"
              label="Business justification"
              error={errors.businessJustification?.[0]}
              required
            >
              <Textarea
                id="business-justification"
                className="min-h-28"
                value={values.businessJustification}
                aria-invalid={Boolean(errors.businessJustification)}
                onChange={(event) => updateValue("businessJustification", event.target.value)}
                disabled={!canEdit}
              />
            </FormField>
            </CardContent>
          </Card>

          <RequisitionTemplatePicker
            templates={templatesQuery.data ?? []}
            disabled={!canEdit}
            onApply={handleApplyTemplate}
          />

          <Card id="line-items">
            <CardHeader>
            <div className="flex items-center justify-between gap-3">
              <CardTitle>Line items</CardTitle>
              <Button type="button" variant="outline" onClick={addLineItem} disabled={!canEdit}>
                <Plus className="h-4 w-4" aria-hidden="true" />
                Add item
              </Button>
            </div>
            </CardHeader>
            <CardContent className="space-y-3">
            <div className="space-y-4">
              {values.lineItems.map((item, index) => (
                <Card key={index}>
                  <CardContent className="pt-4">
                  <div className="grid gap-3 md:grid-cols-2">
                    <FormField
                      htmlFor={`item-name-${index}`}
                      label={`Item name ${index + 1}`}
                      error={errors[`lineItems.${index}.name`]?.[0]}
                    >
                      <Input
                        id={`item-name-${index}`}
                        value={item.name}
                        aria-invalid={Boolean(errors[`lineItems.${index}.name`])}
                        aria-describedby={lineItemsErrorId}
                        onChange={(event) => updateLineItem(index, "name", event.target.value)}
                        disabled={!canEdit}
                      />
                    </FormField>
                    <FormField
                      htmlFor={`quantity-${index}`}
                      label={`Quantity ${index + 1}`}
                      error={errors[`lineItems.${index}.quantity`]?.[0]}
                    >
                      <Input
                        id={`quantity-${index}`}
                        type="number"
                        min="0"
                        value={item.quantity}
                        aria-invalid={Boolean(errors[`lineItems.${index}.quantity`])}
                        aria-describedby={lineItemsErrorId}
                        onChange={(event) => updateLineItem(index, "quantity", event.target.value)}
                        disabled={!canEdit}
                      />
                    </FormField>
                    <FormField
                      htmlFor={`unit-${index}`}
                      label={`Unit ${index + 1}`}
                      error={errors[`lineItems.${index}.unit`]?.[0]}
                    >
                      <Input
                        id={`unit-${index}`}
                        value={item.unit}
                        aria-invalid={Boolean(errors[`lineItems.${index}.unit`])}
                        aria-describedby={lineItemsErrorId}
                        onChange={(event) => updateLineItem(index, "unit", event.target.value)}
                        disabled={!canEdit}
                      />
                    </FormField>
                    <FormField
                      htmlFor={`unit-price-${index}`}
                      label={`Estimated unit price ${index + 1}`}
                      error={errors[`lineItems.${index}.estimatedUnitPrice`]?.[0]}
                    >
                      <Input
                        id={`unit-price-${index}`}
                        type="number"
                        min="0"
                        value={item.estimatedUnitPrice}
                        aria-invalid={Boolean(errors[`lineItems.${index}.estimatedUnitPrice`])}
                        aria-describedby={lineItemsErrorId}
                        onChange={(event) =>
                          updateLineItem(index, "estimatedUnitPrice", event.target.value)
                        }
                        disabled={!canEdit}
                      />
                    </FormField>
                    <FormField
                      htmlFor={`currency-${index}`}
                      label={`Currency ${index + 1}`}
                      error={errors[`lineItems.${index}.currency`]?.[0]}
                    >
                      <Input
                        id={`currency-${index}`}
                        value={item.currency ?? ""}
                        aria-invalid={Boolean(errors[`lineItems.${index}.currency`])}
                        aria-describedby={lineItemsErrorId}
                        onChange={(event) => updateLineItem(index, "currency", event.target.value)}
                        disabled={!canEdit}
                      />
                    </FormField>
                    <Button
                      type="button"
                      variant="outline"
                      className="md:self-end"
                      onClick={() => removeLineItem(index)}
                      aria-label={`Remove line item ${index + 1}`}
                      disabled={!canEdit}
                    >
                      <Trash2 className="h-4 w-4" aria-hidden="true" />
                      Remove
                    </Button>
                  </div>
                  <RequisitionLineItemSuggestionCombobox
                    search={item.name}
                    currency={item.currency ?? values.currency}
                    disabled={!canEdit}
                    onSelect={(suggestion) => {
                      if (!canEdit) return;
                      updateDraftValues((current) => ({
                        ...current,
                        lineItems: current.lineItems.map((currentItem, itemIndex) =>
                          itemIndex === index
                            ? {
                                ...currentItem,
                                name: suggestion.name,
                                unit: suggestion.unit,
                                estimatedUnitPrice: suggestion.estimatedUnitPrice,
                                currency: suggestion.currency,
                              }
                            : currentItem,
                        ),
                      }));
                    }}
                  />
                  </CardContent>
                </Card>
              ))}
            </div>
            {hasLineItemErrors ? (
              <p id="line-items-error" className="text-sm text-red-700">
                {lineItemErrorMessage ?? "Review the highlighted line item fields before submitting."}
              </p>
            ) : null}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Optional context</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
            <div className="grid gap-3 md:grid-cols-2">
              {renderDepartmentField()}
              <FormField htmlFor="project-id" label="Project" error={errors.projectId?.[0]}>
                <NativeSelect
                  id="project-id"
                  value={values.projectId}
                  aria-invalid={Boolean(errors.projectId)}
                  onChange={(event) => updateValue("projectId", event.target.value)}
                  disabled={!canEdit}
                >
                  <option value="">No project</option>
                  {selectableProjects.map((project) => (
                    <option key={project.id} value={project.id}>
                      {project.number} - {project.name}
                    </option>
                  ))}
                </NativeSelect>
              </FormField>
              {projectsQuery.isError ? (
                <p className="text-sm text-amber-700 md:col-span-2">
                  Projects could not be loaded right now. Requisition editing remains available.
                </p>
              ) : null}
              {renderCostCenterField()}
              <div className="md:col-span-2">
                <FormField htmlFor="delivery-location" label="Delivery location" error={errors.deliveryLocation?.[0]}>
                  <Textarea
                    id="delivery-location"
                    className="min-h-20"
                    value={values.deliveryLocation}
                    aria-invalid={Boolean(errors.deliveryLocation)}
                    onChange={(event) => updateValue("deliveryLocation", event.target.value)}
                    disabled={!canEdit}
                  />
                </FormField>
              </div>
            </div>
            </CardContent>
          </Card>
        </div>

        <SubmissionChecklist values={values} />
      </div>

      <SubmitRequisitionDialog
        open={confirmOpen}
        values={values}
        isSubmitting={submitDraft.isPending}
        onCancel={() => setConfirmOpen(false)}
        onConfirm={handleConfirmSubmit}
      />

      {pendingTemplate ? (
        <Dialog open onOpenChange={(open) => (!open ? setPendingTemplate(null) : undefined)}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle id="template-confirm-title">Apply template?</DialogTitle>
              <DialogDescription>
                This draft already has content. Confirm how you want the template to be applied.
              </DialogDescription>
            </DialogHeader>
            <p className="mt-1 text-sm">
              <strong>{pendingTemplate.template.name}</strong> will be applied using the{" "}
              <code>{pendingTemplate.mode}</code> mode.
            </p>
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setPendingTemplate(null)}
                disabled={applyTemplateMutation.isPending}
              >
                Cancel
              </Button>
              <Button
                type="button"
                onClick={() => applyTemplateNow(pendingTemplate.template, pendingTemplate.mode)}
                disabled={applyTemplateMutation.isPending}
              >
                {applyTemplateMutation.isPending ? "Applying" : "Apply template"}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      ) : null}
    </form>
  );
}

function saveStateLabel(
  saveState: "idle" | "unsaved" | "saving" | "saved" | "failed" | "conflict",
) {
  if (saveState === "saving") return "Saving";
  if (saveState === "saved") return "Saved";
  if (saveState === "failed") return "Save failed";
  if (saveState === "conflict") return "Draft conflict";
  return "Unsaved";
}

function requisitionToFormValues(requisition: Requisition): RequisitionFormValues {
  return {
    title: requisition.title,
    businessJustification: requisition.businessJustification,
    neededByDate: requisition.neededByDate ?? "",
    department: requisition.department ?? "",
    projectId: requisition.projectId ?? "",
    costCenter: requisition.costCenter ?? "",
    deliveryLocation: requisition.deliveryLocation ?? "",
    currency: requisition.currency ?? "MYR",
    lineItems: requisition.lineItems.map((item) => ({
      ...item,
    })),
  };
}

function hasMeaningfulDraftContent(values: RequisitionFormValues): boolean {
  if (values.businessJustification.trim()) {
    return true;
  }

  if (
    values.department.trim() ||
    values.projectId.trim() ||
    values.costCenter.trim() ||
    values.deliveryLocation.trim()
  ) {
    return true;
  }

  return values.lineItems.some(
    (item) =>
      item.name.trim() ||
      item.description?.trim() ||
      item.quantity !== 1 ||
      item.unit !== "each" ||
      item.estimatedUnitPrice !== 0 ||
      item.currency !== "MYR",
  );
}

function mergeTemplateDefaults(
  current: RequisitionFormValues,
  defaults: Partial<RequisitionFormValues>,
  mode: RequisitionTemplateMode,
): RequisitionFormValues {
  const nextLineItems = defaults.lineItems?.length
    ? defaults.lineItems.map((item) => ({
        id: item.id,
        name: item.name,
        description: item.description,
        quantity: item.quantity ?? 1,
        unit: item.unit ?? "each",
        estimatedUnitPrice: item.estimatedUnitPrice ?? 0,
        currency: item.currency ?? current.currency ?? "MYR",
        estimatedLineTotal: item.estimatedLineTotal,
      }))
    : current.lineItems;

  if (mode === "replace") {
    return {
      title: defaults.title ?? current.title,
      businessJustification: defaults.businessJustification ?? current.businessJustification,
      neededByDate: defaults.neededByDate ?? current.neededByDate,
      department: defaults.department ?? current.department,
      projectId: defaults.projectId ?? current.projectId,
      costCenter: defaults.costCenter ?? current.costCenter,
      deliveryLocation: defaults.deliveryLocation ?? current.deliveryLocation,
      currency: defaults.currency ?? current.currency,
      lineItems: nextLineItems,
    };
  }

  return {
    ...current,
    title: current.title || defaults.title || "",
    businessJustification: current.businessJustification || defaults.businessJustification || "",
    neededByDate: current.neededByDate || defaults.neededByDate || "",
    department: current.department || defaults.department || "",
    projectId: current.projectId || defaults.projectId || "",
    costCenter: current.costCenter || defaults.costCenter || "",
    deliveryLocation: current.deliveryLocation || defaults.deliveryLocation || "",
    currency: current.currency || defaults.currency || "MYR",
    lineItems:
      current.lineItems.length === 1 && isBlankLineItem(current.lineItems[0]) && defaults.lineItems?.length
        ? nextLineItems
        : current.lineItems,
  };
}

function isBlankLineItem(item: RequisitionFormValues["lineItems"][number]): boolean {
  return (
    !item.name.trim() &&
    !item.description?.trim() &&
    item.quantity === 1 &&
    item.unit === "each" &&
    item.estimatedUnitPrice === 0
  );
}

function collapsedFieldErrors(errors: Record<string, string[]>): Record<string, string[]> {
  return Object.entries(errors).reduce<Record<string, string[]>>((accumulator, [field, messages]) => {
    if (!messages) return accumulator;

    const summaryField = field.startsWith("lineItems.") ? "lineItems" : field;
    accumulator[summaryField] = [...(accumulator[summaryField] ?? []), ...messages];
    return accumulator;
  }, {});
}

function uniqueTextOptions(options: string[], currentValue: string): string[] {
  const normalized = new Set(options.filter((option) => option.trim().length > 0));
  if (currentValue.trim().length > 0) {
    normalized.add(currentValue);
  }
  return [...normalized];
}

function firstFieldError(errors: Record<string, string[]>, prefix: string): string | undefined {
  const entry = Object.entries(errors).find(([field]) => field.startsWith(`${prefix}.`));
  return entry?.[1]?.[0];
}

function requisitionStatusLabel(status: Requisition["status"]): string {
  const labels: Record<Requisition["status"], string> = {
    draft: "Draft",
    submitted: "Submitted",
    pending_approval: "Pending approval",
    changes_requested: "Changes requested",
    approved: "Approved",
    rejected: "Rejected",
    withdrawn: "Withdrawn",
    cancelled: "Cancelled",
  };

  return labels[status];
}

function clearFieldErrors(
  errors: Record<string, string[]>,
  keys: string[],
): Record<string, string[]> {
  const next = { ...errors };
  for (const key of keys) {
    delete next[key];
  }
  return next;
}
