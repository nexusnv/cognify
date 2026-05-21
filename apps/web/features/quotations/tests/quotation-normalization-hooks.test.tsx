import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({
  listQuotationNormalizationsMock: vi.fn(),
  showQuotationNormalizationMock: vi.fn(),
  approveQuotationNormalizationMock: vi.fn(),
  approveQuotationNormalizationWithWarningsMock: vi.fn(),
  saveQuotationNormalizationCorrectionsMock: vi.fn(),
  saveQuotationNormalizationLineMappingsMock: vi.fn(),
  createQuotationNormalizationRevisionMock: vi.fn(),
  retryQuotationVersionNormalizationMock: vi.fn(),
}));

vi.mock("../api/quotation-normalization-api", () => ({
  listQuotationNormalizations: mocks.listQuotationNormalizationsMock,
  showQuotationNormalization: mocks.showQuotationNormalizationMock,
  approveQuotationNormalization: mocks.approveQuotationNormalizationMock,
  approveQuotationNormalizationWithWarnings: mocks.approveQuotationNormalizationWithWarningsMock,
  saveQuotationNormalizationCorrections: mocks.saveQuotationNormalizationCorrectionsMock,
  saveQuotationNormalizationLineMappings: mocks.saveQuotationNormalizationLineMappingsMock,
  createQuotationNormalizationRevision: mocks.createQuotationNormalizationRevisionMock,
  retryQuotationVersionNormalization: mocks.retryQuotationVersionNormalizationMock,
}));

import {
  quotationNormalizationKeys,
  useApproveQuotationNormalization,
  useQuotationNormalization,
  useQuotationNormalizations,
} from "../hooks/use-quotation-normalizations";

describe("quotation normalization hooks", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("passes filters to the list query", async () => {
    mocks.listQuotationNormalizationsMock.mockResolvedValue([{ id: "norm-1" }]);

    const { result } = renderHook(() => useQuotationNormalizations({ status: ["pending"] }), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(mocks.listQuotationNormalizationsMock).toHaveBeenCalledWith({ status: ["pending"] });
  });

  it("passes the normalization id to the detail query", async () => {
    mocks.showQuotationNormalizationMock.mockResolvedValue({ id: "norm-1" });

    const { result } = renderHook(() => useQuotationNormalization("norm-1"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(mocks.showQuotationNormalizationMock).toHaveBeenCalledWith("norm-1");
  });

  it("invalidates the list and detail queries after approval", async () => {
    mocks.approveQuotationNormalizationMock.mockResolvedValue({ id: "norm-1" });

    const queryClient = new QueryClient({
      defaultOptions: {
        queries: { retry: false },
        mutations: { retry: false },
      },
    });
    const invalidateQueries = vi.spyOn(queryClient, "invalidateQueries");
    const { result } = renderHook(() => useApproveQuotationNormalization("norm-1"), {
      wrapper: createWrapper(queryClient),
    });

    await result.current.mutateAsync({ approvalNote: "looks good" });

    expect(mocks.approveQuotationNormalizationMock).toHaveBeenCalledWith("norm-1", {
      approvalNote: "looks good",
    });
    expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: quotationNormalizationKeys.all });
    expect(invalidateQueries).toHaveBeenCalledWith({
      queryKey: quotationNormalizationKeys.detail("norm-1"),
    });
  });
});

function createWrapper(queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: false },
    mutations: { retry: false },
  },
})) {
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
  };
}
