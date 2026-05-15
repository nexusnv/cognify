import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { renderHook } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createRequisitionDraft, updateRequisitionDraft } from "../api/requisitions-api";
import { useSaveRequisitionDraft } from "../hooks/use-save-requisition-draft";
import type { Requisition, RequisitionFormValues } from "../types/requisition-view-model";

vi.mock("../api/requisitions-api", () => ({
  createRequisitionDraft: vi.fn(),
  updateRequisitionDraft: vi.fn(),
}));

const values: RequisitionFormValues = {
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

const requisition = {
  id: "req-1",
  lockVersion: 2,
} as Requisition;

describe("useSaveRequisitionDraft", () => {
  beforeEach(() => {
    vi.mocked(createRequisitionDraft).mockReset();
    vi.mocked(updateRequisitionDraft).mockReset();
  });

  it("rejects update calls that do not include a lock version", async () => {
    const { result } = renderHook(() => useSaveRequisitionDraft(), { wrapper: createWrapper() });

    await expect(
      result.current.mutateAsync({ requisitionId: "req-1", values }),
    ).rejects.toThrow("lockVersion required for updates");

    expect(updateRequisitionDraft).not.toHaveBeenCalled();
    expect(createRequisitionDraft).not.toHaveBeenCalled();
  });

  it("passes the supplied lock version to draft updates", async () => {
    vi.mocked(updateRequisitionDraft).mockResolvedValue(requisition);
    const { result } = renderHook(() => useSaveRequisitionDraft(), { wrapper: createWrapper() });

    await result.current.mutateAsync({ requisitionId: "req-1", values, lockVersion: 1 });

    expect(updateRequisitionDraft).toHaveBeenCalledWith("req-1", values, 1);
  });
});

function createWrapper() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
  };
}
