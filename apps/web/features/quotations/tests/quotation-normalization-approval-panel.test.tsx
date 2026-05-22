import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import type { QuotationNormalization } from "@cognify/api-client/schemas";
import { quotationNormalizationFixtures } from "../mocks/quotation-normalization-fixtures";
import { QuotationNormalizationApprovalPanel } from "../components/quotation-normalization-approval-panel";

describe("QuotationNormalizationApprovalPanel", () => {
  it("disables both approval actions while a submission is pending", async () => {
    const user = userEvent.setup();
    const deferred = createDeferred<void>();
    const normalization = makeNormalization({
      permissions: {
        canEdit: true,
        canApprove: true,
        canApproveWithWarnings: true,
        canRetry: false,
        canCreateRevision: false,
      },
    });
    const onApprove = vi.fn(() => deferred.promise);

    render(
      <QuotationNormalizationApprovalPanel
        normalization={normalization}
        canEdit
        onApprove={onApprove}
        onApproveWithWarnings={vi.fn(async () => undefined)}
      />,
    );

    await user.type(screen.getByLabelText("Approval note"), "Reviewed and approved.");
    await user.click(screen.getByRole("button", { name: "Approve normalization" }));

    await waitFor(() => {
      expect(screen.getByRole("button", { name: "Approve normalization" })).toBeDisabled();
      expect(screen.getByRole("button", { name: "Approve with warnings" })).toBeDisabled();
    });

    deferred.resolve();

    await waitFor(() => {
      expect(screen.getByRole("button", { name: "Approve normalization" })).not.toBeDisabled();
      expect(screen.getByRole("button", { name: "Approve with warnings" })).not.toBeDisabled();
    });
  });

  it("surfaces approval failures in an alert and re-enables the actions", async () => {
    const user = userEvent.setup();
    const normalization = makeNormalization({
      permissions: {
        canEdit: true,
        canApprove: true,
        canApproveWithWarnings: false,
        canRetry: false,
        canCreateRevision: false,
      },
    });
    const onApprove = vi.fn().mockRejectedValue(new Error("Approval failed."));

    render(
      <QuotationNormalizationApprovalPanel
        normalization={normalization}
        canEdit
        onApprove={onApprove}
        onApproveWithWarnings={vi.fn(async () => undefined)}
      />,
    );

    await user.click(screen.getByRole("button", { name: "Approve normalization" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("Approval failed.");
    await waitFor(() => {
      expect(screen.getByRole("button", { name: "Approve normalization" })).not.toBeDisabled();
    });
  });

  it("surfaces backend approval payload messages", async () => {
    const user = userEvent.setup();
    const normalization = makeNormalization({
      permissions: {
        canEdit: true,
        canApprove: true,
        canApproveWithWarnings: false,
        canRetry: false,
        canCreateRevision: false,
      },
    });
    const onApprove = vi.fn().mockRejectedValue({
      error: {
        code: "conflict",
        message: "Resolve blocking issues before approval.",
      },
    });

    render(
      <QuotationNormalizationApprovalPanel
        normalization={normalization}
        canEdit
        onApprove={onApprove}
        onApproveWithWarnings={vi.fn(async () => undefined)}
      />,
    );

    await user.click(screen.getByRole("button", { name: "Approve normalization" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("Resolve blocking issues before approval.");
  });

  it("renders read-only approval content when editing is not allowed even if permissions are optimistic", () => {
    const normalization = makeNormalization({
      status: "ready_for_approval",
      permissions: {
        canEdit: false,
        canApprove: true,
        canApproveWithWarnings: true,
        canRetry: false,
        canCreateRevision: false,
      },
    });

    render(
      <QuotationNormalizationApprovalPanel
        normalization={normalization}
        canEdit={false}
        onApprove={vi.fn(async () => undefined)}
        onApproveWithWarnings={vi.fn(async () => undefined)}
      />,
    );

    expect(screen.getByText("Read-only approval record")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Approve normalization" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Approve with warnings" })).not.toBeInTheDocument();
  });
});

function makeNormalization(overrides: Partial<QuotationNormalization> = {}): QuotationNormalization {
  return {
    ...structuredClone(quotationNormalizationFixtures[0]),
    ...overrides,
  };
}

function createDeferred<T>() {
  let resolve!: (value: T | PromiseLike<T>) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });

  return { promise, resolve, reject };
}
