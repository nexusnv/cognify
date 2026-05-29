"use client";

import { useState } from "react";
import {
  Alert,
  AlertDescription,
  Button,
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
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
      <Dialog
        open={open}
        onOpenChange={(nextOpen) => {
          setOpen(nextOpen);
          if (!nextOpen) {
            setError(null);
          }
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Delegate approval task</DialogTitle>
            <DialogDescription>
              Assign this task to another approver for a fixed window.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
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
              <Input
                aria-label="Starts"
                className="mt-1 min-h-11 w-full rounded-md border px-3 text-base font-normal"
                type="date"
                value={startsAt}
                onChange={(event) => setStartsAt(event.target.value)}
              />
            </label>
            <label className="block text-sm font-medium">
              Ends
              <Input
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
          {error ? (
            <Alert variant="destructive">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          ) : null}
          <DialogFooter>
            <Button variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button disabled={pending} onClick={handleSubmit}>
              {pending ? "Delegating" : "Confirm delegation"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
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
