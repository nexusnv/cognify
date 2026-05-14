import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { requisitionFixtures } from "../../requisitions/mocks/requisitions-fixtures";
import { RequisitionDetailPage } from "../../requisitions/workflows/requisition-detail-page";

const requisitionHooks = vi.hoisted(() => ({
  useRequisition: vi.fn(),
  useRequisitionActivity: vi.fn(),
}));

vi.mock("../../requisitions/hooks/use-requisition", () => ({
  useRequisition: requisitionHooks.useRequisition,
  useRequisitionActivity: requisitionHooks.useRequisitionActivity,
}));

vi.mock("../../attachments/components/attachment-list", () => ({
  AttachmentList: () => null,
}));

vi.mock("../../attachments/components/attachment-uploader", () => ({
  AttachmentUploader: () => null,
}));

function renderWithQuery(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

describe("requisition recent-record tracking", () => {
  beforeEach(() => {
    window.localStorage.clear();
    window.sessionStorage.clear();
    window.localStorage.setItem("cognify.activeTenantId", "tenant-1");
    requisitionHooks.useRequisition.mockReset();
    requisitionHooks.useRequisitionActivity.mockReset();
  });

  it("records opened requisitions in session storage", async () => {
    const requisition = requisitionFixtures[0];
    if (!requisition) throw new Error("Expected requisition fixture");

    requisitionHooks.useRequisition.mockReturnValue({
      data: requisition,
      isLoading: false,
      isError: false,
    });
    requisitionHooks.useRequisitionActivity.mockReturnValue({
      data: { data: [] },
      isLoading: false,
      isError: false,
    });

    renderWithQuery(<RequisitionDetailPage requisitionId={requisition.id} />);

    await waitFor(() => {
      expect(JSON.parse(window.sessionStorage.getItem("cognify.recentRecords.v1") ?? "[]")).toEqual([
        {
          type: "requisition",
          id: requisition.id,
          title: requisition.title,
          subtitle: requisition.number,
          status: requisition.status,
          href: `/requisitions/${requisition.id}`,
          updatedAt: requisition.updatedAt,
        },
      ]);
    });
  });
});
