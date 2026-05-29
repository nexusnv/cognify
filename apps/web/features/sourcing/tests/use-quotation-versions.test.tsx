import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { renderHook } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { storeActiveTenantId } from "@/features/identity/api/identity-api";

const mocks = vi.hoisted(() => ({
  createQuotationVersionMock: vi.fn(),
  listQuotationVersionsMock: vi.fn(),
  showQuotationVersionMock: vi.fn(),
}));

vi.mock("../api/quotation-api", () => ({
  createQuotationVersion: mocks.createQuotationVersionMock,
  listQuotationVersions: mocks.listQuotationVersionsMock,
  showQuotationVersion: mocks.showQuotationVersionMock,
}));

import { quotationVersionKeys, useCreateQuotationVersion, useQuotationVersion } from "../hooks/use-quotation-versions";

describe("useCreateQuotationVersion", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    window.localStorage.clear();
  });

  it("stores the created version under the version number detail key", async () => {
    storeActiveTenantId("tenant-1");
    const createdVersion = {
      id: "501",
      quotationId: "quotation-1",
      versionNumber: 7,
    };
    mocks.createQuotationVersionMock.mockResolvedValue(createdVersion);

    const queryClient = new QueryClient({
      defaultOptions: {
        queries: { retry: false },
        mutations: { retry: false },
      },
    });
    const setQueryData = vi.spyOn(queryClient, "setQueryData");
    const { result } = renderHook(() => useCreateQuotationVersion("quotation-1", "invitation-1"), {
      wrapper: createWrapper(queryClient),
    });

    await result.current.mutateAsync({} as never);

    expect(mocks.createQuotationVersionMock).toHaveBeenCalledWith("quotation-1", {}, "tenant-1");
    expect(setQueryData).toHaveBeenCalledWith(
      quotationVersionKeys.detail("quotation-1", 7, "tenant-1"),
      createdVersion,
    );
  });

  it("does not fetch quotation version details for non-finite version numbers", () => {
    storeActiveTenantId("tenant-1");

    renderHook(() => useQuotationVersion("quotation-1", Number.NaN), {
      wrapper: createWrapper(),
    });

    expect(mocks.showQuotationVersionMock).not.toHaveBeenCalled();
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
