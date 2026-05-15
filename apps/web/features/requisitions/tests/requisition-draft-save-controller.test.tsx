import { act, renderHook } from "@testing-library/react";
import { describe, expect, it, vi, beforeEach, afterEach } from "vitest";
import { useRequisitionDraftSaveController } from "../hooks/use-requisition-draft-save-controller";
import type { RequisitionFormValues } from "../types/requisition-view-model";

const baseValues: RequisitionFormValues = {
  title: "Laptop refresh",
  businessJustification: "Replace unsupported laptops.",
  neededByDate: "2026-06-15",
  department: "IT",
  projectId: "Project Atlas",
  costCenter: "IT-210",
  deliveryLocation: "Kuala Lumpur",
  currency: "MYR",
  lineItems: [
    {
      name: "Laptop",
      quantity: 1,
      unit: "each",
      estimatedUnitPrice: 1800,
      currency: "MYR",
    },
  ],
};

describe("useRequisitionDraftSaveController", () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it("creates the first draft from a manual save and tracks the returned lock version", async () => {
    const createDraft = vi.fn().mockResolvedValue({ id: "req-1", lockVersion: 0 });
    const updateDraft = vi.fn();

    const { result } = renderHook(() =>
      useRequisitionDraftSaveController({
        createDraft,
        updateDraft,
      }),
    );

    await act(async () => {
      await result.current.saveNow(baseValues);
    });

    expect(createDraft).toHaveBeenCalledWith(baseValues);
    expect(updateDraft).not.toHaveBeenCalled();
    expect(result.current.requisitionId).toBe("req-1");
    expect(result.current.lockVersion).toBe(0);
    expect(result.current.saveState).toBe("saved");
    expect(result.current.lastFailedValues).toBeNull();
  });

  it("autosaves an existing draft with the latest lock version", async () => {
    const createDraft = vi.fn();
    const updateDraft = vi.fn().mockResolvedValue({ id: "req-1", lockVersion: 2 });

    const { result } = renderHook(() =>
      useRequisitionDraftSaveController({
        initialRequisition: { id: "req-1", lockVersion: 1 },
        createDraft,
        updateDraft,
        autosaveDelayMs: 20,
      }),
    );

    act(() => {
      result.current.scheduleAutosave(baseValues);
    });

    await act(async () => {
      await vi.advanceTimersByTimeAsync(20);
    });

    expect(updateDraft).toHaveBeenCalledWith("req-1", baseValues, 1);
    expect(createDraft).not.toHaveBeenCalled();
    expect(result.current.requisitionId).toBe("req-1");
    expect(result.current.lockVersion).toBe(2);
    expect(result.current.saveState).toBe("saved");
    expect(result.current.lastFailedValues).toBeNull();
  });

  it("marks a draft conflict without clearing the failed values", async () => {
    const conflict = {
      data: {
        error: {
          code: "draft_conflict",
          message: "The draft has changed since it was loaded.",
        },
      },
    };
    const createDraft = vi.fn();
    const updateDraft = vi.fn().mockRejectedValue(conflict);

    const { result } = renderHook(() =>
      useRequisitionDraftSaveController({
        initialRequisition: { id: "req-1", lockVersion: 3 },
        createDraft,
        updateDraft,
      }),
    );

    await act(async () => {
      await result.current.saveNow(baseValues);
    });

    expect(updateDraft).toHaveBeenCalledWith("req-1", baseValues, 3);
    expect(result.current.requisitionId).toBe("req-1");
    expect(result.current.lockVersion).toBe(3);
    expect(result.current.saveState).toBe("conflict");
    expect(result.current.lastFailedValues).toEqual(baseValues);
  });

  it("serializes overlapping saves so the second update uses the refreshed lock version", async () => {
    const firstSave = deferred<{ id: string; lockVersion: number }>();
    const secondSave = deferred<{ id: string; lockVersion: number }>();
    const nextValues = { ...baseValues, title: "Laptop refresh v2" };
    const createDraft = vi.fn();
    const updateDraft = vi
      .fn()
      .mockImplementationOnce(() => firstSave.promise)
      .mockImplementationOnce(() => secondSave.promise);

    const { result } = renderHook(() =>
      useRequisitionDraftSaveController({
        initialRequisition: { id: "req-1", lockVersion: 1 },
        createDraft,
        updateDraft,
      }),
    );

    await act(async () => {
      const firstResult = result.current.saveNow(baseValues);
      const secondResult = result.current.saveNow(nextValues);

      await Promise.resolve();
      expect(updateDraft).toHaveBeenCalledTimes(1);
      expect(updateDraft).toHaveBeenCalledWith("req-1", baseValues, 1);

      firstSave.resolve({ id: "req-1", lockVersion: 2 });
      await Promise.resolve();
      await Promise.resolve();

      expect(updateDraft).toHaveBeenCalledTimes(2);
      expect(updateDraft).toHaveBeenLastCalledWith("req-1", nextValues, 2);

      secondSave.resolve({ id: "req-1", lockVersion: 3 });
      await Promise.all([firstResult, secondResult]);
    });

    expect(result.current.lockVersion).toBe(3);
    expect(result.current.saveState).toBe("saved");
  });
});

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (error: unknown) => void;
  const promise = new Promise<T>((promiseResolve, promiseReject) => {
    resolve = promiseResolve;
    reject = promiseReject;
  });

  return { promise, resolve, reject };
}
