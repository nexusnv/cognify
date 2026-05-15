"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import type { RequisitionFormValues } from "../types/requisition-view-model";

export type RequisitionDraftSaveState = "idle" | "unsaved" | "saving" | "saved" | "failed" | "conflict";

export type RequisitionDraftSnapshot = {
  id: string;
  lockVersion: number;
};

export type RequisitionDraftSaveControllerOptions = {
  initialRequisition?: RequisitionDraftSnapshot;
  createDraft: (values: RequisitionFormValues) => Promise<RequisitionDraftSnapshot>;
  updateDraft: (
    requisitionId: string,
    values: RequisitionFormValues,
    lockVersion: number,
  ) => Promise<RequisitionDraftSnapshot>;
  autosaveDelayMs?: number;
};

function isDraftConflict(error: unknown): boolean {
  if (typeof error !== "object" || error === null) return false;

  const candidate = error as {
    code?: unknown;
    data?: { error?: { code?: unknown } };
    error?: { code?: unknown };
  };

  return (
    candidate.code === "draft_conflict" ||
    candidate.error?.code === "draft_conflict" ||
    candidate.data?.error?.code === "draft_conflict"
  );
}

export function useRequisitionDraftSaveController({
  initialRequisition,
  createDraft,
  updateDraft,
  autosaveDelayMs = 1200,
}: RequisitionDraftSaveControllerOptions) {
  const [requisitionId, setRequisitionId] = useState(initialRequisition?.id);
  const [lockVersion, setLockVersion] = useState(initialRequisition?.lockVersion ?? 0);
  const [saveState, setSaveState] = useState<RequisitionDraftSaveState>("idle");
  const [lastFailedValues, setLastFailedValues] = useState<RequisitionFormValues | null>(null);
  const [lastError, setLastError] = useState<unknown | null>(null);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const requisitionIdRef = useRef(initialRequisition?.id);
  const lockVersionRef = useRef(initialRequisition?.lockVersion ?? 0);

  useEffect(() => {
    return () => {
      if (timerRef.current) {
        clearTimeout(timerRef.current);
      }
    };
  }, []);

  const clearAutosaveTimer = useCallback(() => {
    if (!timerRef.current) return;

    clearTimeout(timerRef.current);
    timerRef.current = null;
  }, []);

  const applySavedDraft = useCallback((requisition: RequisitionDraftSnapshot) => {
    requisitionIdRef.current = requisition.id;
    lockVersionRef.current = requisition.lockVersion;
    setRequisitionId(requisition.id);
    setLockVersion(requisition.lockVersion);
    setLastFailedValues(null);
    setLastError(null);
    setSaveState("saved");
  }, []);

  const saveNow = useCallback(
    async (values: RequisitionFormValues) => {
      clearAutosaveTimer();
      setSaveState("saving");

      try {
        const savedDraft = requisitionIdRef.current
          ? await updateDraft(requisitionIdRef.current, values, lockVersionRef.current)
          : await createDraft(values);

        applySavedDraft(savedDraft);
        return savedDraft;
      } catch (error) {
        setLastFailedValues(values);
        setLastError(error);
        setSaveState(isDraftConflict(error) ? "conflict" : "failed");
        return undefined;
      }
    },
    [applySavedDraft, clearAutosaveTimer, createDraft, updateDraft],
  );

  const scheduleAutosave = useCallback(
    (values: RequisitionFormValues) => {
      setSaveState("unsaved");
      clearAutosaveTimer();

      if (!requisitionIdRef.current) {
        return;
      }

      timerRef.current = setTimeout(() => {
        void saveNow(values);
      }, autosaveDelayMs);
    },
    [autosaveDelayMs, clearAutosaveTimer, saveNow],
  );

  return {
    requisitionId,
    lockVersion,
    saveState,
    lastFailedValues,
    lastError,
    saveNow,
    scheduleAutosave,
    syncSavedDraft: applySavedDraft,
  };
}
