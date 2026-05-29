"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { getApiErrorCode, getApiErrorMessage, getApiValidationErrors } from "@cognify/api-client";
import { Alert, AlertDescription, AlertTitle, Button, Input, NativeSelect, Textarea } from "@cognify/ui";
import { FormField } from "@/components/forms/form-field";
import { useCurrentUser } from "@/features/identity/hooks/use-current-user";
import { useCreateProject, useUpdateProject } from "../hooks/use-project-actions";
import { projectFormSchema } from "../schemas/project-form-schema";
import type { ProcurementProject, ProjectFormValues } from "../types/project-view-model";

const fieldLabels: Record<keyof ProjectFormValues, string> = {
  name: "Project name is required",
  charter: "Charter is invalid",
  ownerId: "Owner is required",
  budgetAmount: "Budget amount is invalid",
  currency: "Currency is invalid",
  department: "Department is invalid",
  costCenter: "Cost center is invalid",
  targetStartDate: "Target start date is invalid",
  targetCompletionDate: "Target completion date is invalid",
};

export function ProjectForm({
  mode,
  project,
}: {
  mode: "create" | "edit";
  project?: ProcurementProject;
}) {
  const router = useRouter();
  const createMutation = useCreateProject();
  const updateMutation = useUpdateProject(project?.id ?? "");
  const currentUserQuery = useCurrentUser();
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [values, setValues] = useState<ProjectFormValues>(() => initialProjectFormValues(project));
  const formKey = mode === "edit" && project ? project.id : "create";

  const ownerOptions = useMemo(() => {
    const current = currentUserQuery.data?.data.user;
    const options = current
      ? [
          {
            id: current.id,
            name: current.name,
            email: current.email,
          },
        ]
      : [];

    if (mode === "edit" && project) {
      const existingOwner = {
        id: project.owner.id,
        name: project.owner.name,
        email: project.owner.email,
      };

      if (!options.some((owner) => owner.id === existingOwner.id)) {
        options.push(existingOwner);
      }
    }

    return options;
  }, [currentUserQuery.data, mode, project]);

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setFormError(null);

    const parsed = projectFormSchema.safeParse(values);
    if (!parsed.success) {
      setErrors(parsed.error.flatten().fieldErrors);
      return;
    }

    setErrors({});

    try {
      if (mode === "create") {
        const created = await createMutation.mutateAsync(parsed.data);
        router.push(`/projects/${created.id}`);
        return;
      }

      if (!project) return;
      await updateMutation.mutateAsync(parsed.data);
      router.push(`/projects/${project.id}`);
    } catch (error) {
      const validationErrors = getApiValidationErrors(error);
      if (Object.keys(validationErrors).length > 0) {
        setErrors(validationErrors);
        return;
      }

      const code = getApiErrorCode(error);
      const status = typeof error === "object" && error !== null && "status" in error
        ? Number((error as { status?: unknown }).status)
        : null;
      if (code === "forbidden" || status === 403) {
        setFormError("You do not have permission to save this project.");
        return;
      }

      if (code === "unauthenticated" || status === 401) {
        setFormError("Your session expired. Sign in again to save this project.");
        return;
      }

      if (code === "not_found" || status === 404) {
        setFormError("This project could not be found.");
        return;
      }

      if (code === "conflict" || code === "draft_conflict" || status === 409) {
        setFormError("This project changed while you were editing it. Reload and try again.");
        return;
      }

      if (code === "server_error" || code === "too_many_requests" || status === 429 || (status !== null && status >= 500)) {
        setFormError("Unable to save project right now. Try again.");
        return;
      }

      setFormError(getApiErrorMessage(error));
    }
  }

  const summaryErrors = Object.entries(errors).flatMap(([key, messages]) =>
    (messages ?? []).map((message) => ({
      field: key,
      fieldId: key,
      message: message || fieldLabels[key as keyof ProjectFormValues],
    })),
  );

  return (
    <form key={formKey} className="space-y-4" onSubmit={handleSubmit} noValidate>
      {summaryErrors.length > 0 || formError ? (
        <Alert variant={summaryErrors.length > 0 ? "default" : "destructive"}>
          <AlertTitle>
            {summaryErrors.length > 0
              ? "Resolve the highlighted project fields before continuing."
              : "Project could not be saved."}
          </AlertTitle>
          <AlertDescription>
            {summaryErrors.length > 0 ? (
              <ul className="ml-4 list-disc space-y-1">
                {summaryErrors.map((error) => (
                  <li key={`${error.field}-${error.message}`}>
                    <a className="underline underline-offset-4" href={`#${error.fieldId}`}>
                      {error.message}
                    </a>
                  </li>
                ))}
              </ul>
            ) : (
              formError
            )}
          </AlertDescription>
        </Alert>
      ) : null}

      <div className="grid gap-4 md:grid-cols-2">
        <FormField htmlFor="name" label="Project name" required>
          <Input
            id="name"
            aria-invalid={Boolean(errors.name)}
            value={values.name}
            onChange={(event) => setValues((current) => ({ ...current, name: event.target.value }))}
          />
        </FormField>

        <FormField htmlFor="ownerId" label="Owner" required>
          <NativeSelect
            id="ownerId"
            aria-invalid={Boolean(errors.ownerId)}
            value={values.ownerId}
            onChange={(event) =>
              setValues((current) => ({ ...current, ownerId: event.target.value }))
            }
          >
            <option value="">Select owner</option>
            {ownerOptions.map((owner) => (
              <option key={owner.id} value={owner.id}>
                {owner.name} ({owner.email})
              </option>
            ))}
          </NativeSelect>
        </FormField>

        <FormField htmlFor="budgetAmount" label="Budget" required>
          <Input
            id="budgetAmount"
            aria-invalid={Boolean(errors.budgetAmount)}
            value={values.budgetAmount}
            inputMode="decimal"
            onChange={(event) =>
              setValues((current) => ({ ...current, budgetAmount: event.target.value }))
            }
          />
        </FormField>

        <FormField htmlFor="currency" label="Currency" required>
          <Input
            id="currency"
            aria-invalid={Boolean(errors.currency)}
            value={values.currency}
            maxLength={3}
            onChange={(event) =>
              setValues((current) => ({ ...current, currency: event.target.value.toUpperCase() }))
            }
          />
        </FormField>

        <FormField htmlFor="department" label="Department">
          <Input
            id="department"
            aria-invalid={Boolean(errors.department)}
            value={values.department}
            onChange={(event) =>
              setValues((current) => ({ ...current, department: event.target.value }))
            }
          />
        </FormField>

        <FormField htmlFor="costCenter" label="Cost center">
          <Input
            id="costCenter"
            aria-invalid={Boolean(errors.costCenter)}
            value={values.costCenter}
            onChange={(event) =>
              setValues((current) => ({ ...current, costCenter: event.target.value }))
            }
          />
        </FormField>

        <FormField htmlFor="targetStartDate" label="Target start">
          <Input
            id="targetStartDate"
            type="date"
            aria-invalid={Boolean(errors.targetStartDate)}
            value={values.targetStartDate}
            onChange={(event) =>
              setValues((current) => ({ ...current, targetStartDate: event.target.value }))
            }
          />
        </FormField>

        <FormField
          htmlFor="targetCompletionDate"
          label="Target completion"
        >
          <Input
            id="targetCompletionDate"
            type="date"
            aria-invalid={Boolean(errors.targetCompletionDate)}
            value={values.targetCompletionDate}
            onChange={(event) =>
              setValues((current) => ({ ...current, targetCompletionDate: event.target.value }))
            }
          />
        </FormField>
      </div>

      <FormField htmlFor="charter" label="Charter">
        <Textarea
          id="charter"
          aria-invalid={Boolean(errors.charter)}
          value={values.charter}
          onChange={(event) => setValues((current) => ({ ...current, charter: event.target.value }))}
        />
      </FormField>

      <div className="flex items-center justify-end gap-2">
        <Button type="submit" disabled={createMutation.isPending || updateMutation.isPending}>
          {createMutation.isPending || updateMutation.isPending
            ? "Saving"
            : mode === "create"
              ? "Create project"
              : "Save project"}
        </Button>
      </div>
    </form>
  );
}

function initialProjectFormValues(project?: ProcurementProject): ProjectFormValues {
  return {
    name: project?.name ?? "",
    charter: project?.charter ?? "",
    ownerId: project?.owner.id ?? "",
    budgetAmount: project?.budgetAmount?.toFixed(2) ?? "",
    currency: project?.currency ?? "MYR",
    department: project?.department ?? "",
    costCenter: project?.costCenter ?? "",
    targetStartDate: project?.targetStartDate ?? "",
    targetCompletionDate: project?.targetCompletionDate ?? "",
  };
}
