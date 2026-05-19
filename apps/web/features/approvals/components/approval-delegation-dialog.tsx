"use client";

import { useState } from "react";
import { Button, NativeSelect, Textarea } from "@cognify/ui";
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
        approvalDelegationId: Number(delegation.id),
        lockVersion,
      });

      setOpen(false);
      setDelegateId("");
      setReason("");
    } catch (caught) {
      setError(errorMessage(caught));
    }
  }

  return (
    <>
      <Button variant="outline" onClick={() => setOpen(true)}>
        Delegate
      </Button>
      {!open ? null : (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div
            role="dialog"
            aria-modal="true"
            aria-label="Delegate approval task"
            className="w-full max-w-lg rounded-md border bg-background p-5 shadow-lg"
          >
            <h2 className="text-lg font-semibold">Delegate approval task</h2>
            <div className="mt-4 space-y-4">
              <label className="block text-sm font-medium">
                Delegate
                <NativeSelect
                  aria-label="Delegate"
                  className="mt-1"
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
              </label>
              <label className="block text-sm font-medium">
                Starts
                <input
                  aria-label="Starts"
                  className="mt-1 min-h-11 w-full rounded-md border px-3 text-base font-normal"
                  type="date"
                  value={startsAt}
                  onChange={(event) => setStartsAt(event.target.value)}
                />
              </label>
              <label className="block text-sm font-medium">
                Ends
                <input
                  aria-label="Ends"
                  className="mt-1 min-h-11 w-full rounded-md border px-3 text-base font-normal"
                  type="date"
                  value={endsAt}
                  onChange={(event) => setEndsAt(event.target.value)}
                />
              </label>
              <label className="block text-sm font-medium">
                Reason
                <Textarea
                  aria-label="Delegation reason"
                  className="mt-1"
                  value={reason}
                  onChange={(event) => setReason(event.target.value)}
                />
              </label>
            </div>
            {error ? <p className="mt-4 text-sm text-red-700">{error}</p> : null}
            <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <Button variant="outline" onClick={() => setOpen(false)}>
                Cancel
              </Button>
              <Button disabled={pending} onClick={handleSubmit}>
                {pending ? "Delegating" : "Confirm delegation"}
              </Button>
            </div>
          </div>
        </div>
      )}
    </>
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
