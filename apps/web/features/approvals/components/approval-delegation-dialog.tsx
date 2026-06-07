"use client";

import { useState } from "react";
import {
  Alert,
  AlertDescription,
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
  Button,
  Field,
  FieldContent,
  FieldDescription,
  FieldGroup,
  FieldLabel,
  Input,
  NativeSelect,
  Textarea,
} from "@cognify/ui";
import {
  useApprovalDelegationCandidates,
  useCreateApprovalDelegation,
  useDelegateApprovalTask,
} from "../hooks/use-approval-delegations";

type ApprovalDelegationDialogProps = {
  taskId: string;
  lockVersion: number;
  isPending?: boolean;
};

export function ApprovalDelegationDialog({
  taskId,
  lockVersion,
  isPending = false,
}: ApprovalDelegationDialogProps) {
  const [open, setOpen] = useState(false);
  const [delegateId, setDelegateId] = useState("");
  const [startsAt, setStartsAt] = useState(() => todayDateInputValue());
  const [endsAt, setEndsAt] = useState(() => tomorrowDateInputValue());
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const candidatesQuery = useApprovalDelegationCandidates();
  const createDelegation = useCreateApprovalDelegation();
  const delegateTask = useDelegateApprovalTask(taskId);
  const pending = isPending || createDelegation.isPending || delegateTask.isPending;
  const delegateOptions = (candidatesQuery.data ?? [])
    .filter((candidate, index, candidates) => candidates.findIndex((item) => item.id === candidate.id) === index)
    .map((candidate) => ({
      id: candidate.id,
      label: candidate.name,
    }));

  async function handleSubmit() {
    if (!delegateId || !startsAt || !endsAt || !reason.trim()) {
      setError("Delegate, effective dates, and reason are required.");
      return;
    }

    setError(null);
    try {
      const delegation = await createDelegation.mutateAsync({
        delegateId: Number(delegateId),
        scope: "task_specific",
        startsAt: dateInputToIso(startsAt),
        endsAt: dateInputToIso(endsAt),
        reason: reason.trim(),
      });

      await delegateTask.mutateAsync({
        approvalDelegationId: normalizeDelegationId(delegation.id),
        lockVersion,
      });

      setOpen(false);
      setDelegateId("");
      setReason("");
      setError(null);
    } catch (caught) {
      setError(errorMessage(caught));
    }
  }

  return (
    <AlertDialog open={open} onOpenChange={setOpen}>
      <AlertDialogTrigger asChild>
        <Button variant="outline">Delegate</Button>
      </AlertDialogTrigger>
      <AlertDialogContent className="max-w-lg">
        <AlertDialogHeader>
          <AlertDialogTitle>Delegate approval task</AlertDialogTitle>
          <AlertDialogDescription>
            Reassign this task temporarily while preserving the original assignee.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <FieldGroup className="space-y-4">
          <Field>
            <FieldLabel htmlFor="approval-delegate">Delegate</FieldLabel>
            <FieldContent>
              <NativeSelect
                id="approval-delegate"
                aria-label="Delegate"
                value={delegateId}
                onChange={(event) => setDelegateId(event.target.value)}
              >
                <option value="">Select delegate</option>
                {delegateOptions.map((option) => (
                  <option key={option.id} value={option.id}>
                    {option.label}
                  </option>
                ))}
              </NativeSelect>
            </FieldContent>
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field>
              <FieldLabel htmlFor="delegation-starts">Starts</FieldLabel>
              <FieldContent>
                <Input
                  id="delegation-starts"
                  aria-label="Starts"
                  type="date"
                  value={startsAt}
                  onChange={(event) => setStartsAt(event.target.value)}
                />
              </FieldContent>
            </Field>
            <Field>
              <FieldLabel htmlFor="delegation-ends">Ends</FieldLabel>
              <FieldContent>
                <Input
                  id="delegation-ends"
                  aria-label="Ends"
                  type="date"
                  value={endsAt}
                  onChange={(event) => setEndsAt(event.target.value)}
                />
              </FieldContent>
            </Field>
          </div>
          <Field>
            <FieldLabel htmlFor="delegation-reason">Reason</FieldLabel>
            <FieldContent>
              <Textarea
                id="delegation-reason"
                aria-label="Delegation reason"
                value={reason}
                onChange={(event) => setReason(event.target.value)}
              />
              <FieldDescription>Explain why this task is being delegated.</FieldDescription>
            </FieldContent>
          </Field>
        </FieldGroup>
        {error ? (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        ) : null}
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <Button disabled={pending} onClick={handleSubmit}>
            {pending ? "Delegating" : "Confirm delegation"}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}

function todayDateInputValue(): string {
  return new Date().toISOString().slice(0, 10);
}

function tomorrowDateInputValue(): string {
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);

  return tomorrow.toISOString().slice(0, 10);
}

function dateInputToIso(value: string): string {
  return new Date(`${value}T00:00:00.000Z`).toISOString();
}

function normalizeDelegationId(value: string | number): number {
  if (typeof value === "number" && Number.isFinite(value)) {
    return value;
  }

  const numericValue = Number(value);
  if (Number.isFinite(numericValue)) {
    return numericValue;
  }

  const match = String(value).match(/(\d+)$/);
  if (match) {
    return Number(match[1]);
  }

  return Number.NaN;
}

function errorMessage(caught: unknown): string {
  const directMessage = apiErrorMessage(caught);
  if (directMessage) {
    return directMessage;
  }

  if (caught && typeof caught === "object" && "data" in caught) {
    const wrappedMessage = apiErrorMessage(caught.data);
    if (wrappedMessage) {
      return wrappedMessage;
    }
  }

  if (caught instanceof Error) {
    return caught.message;
  }

  return "Approval delegation could not be completed.";
}

function apiErrorMessage(value: unknown): string | null {
  if (!value || typeof value !== "object" || !("error" in value) || !value.error || typeof value.error !== "object") {
    return null;
  }

  if ("details" in value.error && value.error.details && typeof value.error.details === "object") {
    const fields = "fields" in value.error.details ? value.error.details.fields : null;
    if (fields && typeof fields === "object") {
      const messages = Object.values(fields)
        .flatMap((fieldMessages) => (Array.isArray(fieldMessages) ? fieldMessages : []))
        .filter((message): message is string => typeof message === "string");

      if (messages.length > 0) {
        return messages.join(" ");
      }
    }
  }

  if ("message" in value.error && typeof value.error.message === "string") {
    return value.error.message;
  }

  return null;
}
