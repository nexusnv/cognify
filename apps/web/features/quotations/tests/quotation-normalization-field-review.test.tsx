import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { quotationNormalizationFixtures } from "../mocks/quotation-normalization-fixtures";
import { QuotationNormalizationFieldReview } from "../components/quotation-normalization-field-review";

describe("QuotationNormalizationFieldReview", () => {
  it("disables save while a correction is saving and surfaces failures without duplicate submits", async () => {
    const user = userEvent.setup();
    const deferred = createDeferred<void>();
    const onSave = vi.fn(() => deferred.promise);
    const consoleError = vi.spyOn(console, "error").mockImplementation(() => undefined);
    const normalization = quotationNormalizationFixtures[0];

    render(
      <QuotationNormalizationFieldReview
        field={normalization.fields[0]}
        issues={[normalization.issues[0]]}
        canEdit
        onSave={onSave}
      />,
    );

    await user.type(screen.getByLabelText("Correction note"), "Confirmed from quotation.");
    await user.click(screen.getByRole("button", { name: "Save correction" }));
    await user.click(screen.getByRole("button", { name: "Save correction" }));

    expect(onSave).toHaveBeenCalledTimes(1);
    expect(screen.getByRole("button", { name: "Save correction" })).toBeDisabled();

    deferred.reject(new Error("Correction failed."));

    expect(await screen.findByRole("alert")).toHaveTextContent("Correction failed.");
    await waitFor(() => {
      expect(screen.getByRole("button", { name: "Save correction" })).not.toBeDisabled();
    });
    expect(consoleError).toHaveBeenCalled();

    consoleError.mockRestore();
  });
});

function createDeferred<T>() {
  let resolve!: (value: T | PromiseLike<T>) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });

  return { promise, resolve, reject };
}
