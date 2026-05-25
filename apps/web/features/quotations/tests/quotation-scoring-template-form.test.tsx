import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it } from "vitest";
import { server } from "@/tests/msw/server";
import { quotationScoringHandlers, resetQuotationScoringMockState } from "../mocks/quotation-scoring-handlers";
import { QuotationScoringTemplateForm } from "../components/quotation-scoring-template-form";
import { QuotationScoringTemplateFormPage } from "../workflows/quotation-scoring-template-form-page";
import { QuotationScoringTemplateListPage } from "../workflows/quotation-scoring-template-list-page";

describe("quotation scoring template UI", () => {
  beforeEach(() => {
    resetQuotationScoringMockState();
    window.localStorage.clear();
    window.localStorage.setItem("cognify.activeTenantId", "1");
    server.use(...quotationScoringHandlers);
  });

  it("renders active and inactive scoring templates for admins", async () => {
    render(<QuotationScoringTemplateListPage />, { wrapper: TestProviders });

    expect(await screen.findByText("Balanced RFQ Evaluation")).toBeInTheDocument();
    expect(screen.getByText("Technical Fit")).toBeInTheDocument();
    expect(screen.getByText("Legacy Cost Only")).toBeInTheDocument();
    expect(screen.getAllByText("Active").length).toBeGreaterThan(0);
    expect(screen.getByText("Inactive")).toBeInTheDocument();
  });

  it("validates that a template has at least one criterion", async () => {
    const user = userEvent.setup();
    render(<QuotationScoringTemplateForm onSave={() => undefined} />);

    fireEvent.change(screen.getByLabelText("Template name"), { target: { value: "No criteria" } });
    await user.click(screen.getByRole("button", { name: "Remove criterion" }));
    await user.click(screen.getByRole("button", { name: "Save scoring template" }));

    expect(screen.getByRole("alert")).toHaveTextContent("Add at least one criterion.");
  });

  it("adds removes and reorders criteria", async () => {
    const user = userEvent.setup();
    render(<QuotationScoringTemplateForm onSave={() => undefined} />);

    fireEvent.change(screen.getByLabelText("Template name"), { target: { value: "Operational fit" } });
    fireEvent.change(screen.getByLabelText("Label"), { target: { value: "Cost" } });
    await user.click(screen.getByRole("button", { name: "Add criterion" }));
    const rows = screen.getAllByTestId("criterion-row");
    fireEvent.change(within(rows[1]).getByLabelText("Label"), { target: { value: "Delivery" } });
    await user.click(within(rows[1]).getByRole("button", { name: "Move up" }));

    expect(within(screen.getAllByTestId("criterion-row")[0]).getByLabelText("Label")).toHaveValue("Delivery");

    await user.click(within(screen.getAllByTestId("criterion-row")[0]).getByRole("button", { name: "Remove criterion" }));
    expect(screen.getAllByTestId("criterion-row")).toHaveLength(1);
  });

  it("saves a scoring template with criterion weights and max scores", async () => {
    const user = userEvent.setup();
    render(<QuotationScoringTemplateFormPage templateId="new" />, { wrapper: TestProviders });

    fireEvent.change(screen.getByLabelText("Template name"), { target: { value: "Commercial balance" } });
    fireEvent.change(screen.getByLabelText("Label"), { target: { value: "Evaluated cost" } });
    fireEvent.change(screen.getByLabelText("Weight"), { target: { value: "60" } });
    fireEvent.change(screen.getByLabelText("Max score"), { target: { value: "10" } });
    await user.click(screen.getByRole("button", { name: "Save scoring template" }));

    await waitFor(() => {
      expect(screen.queryByRole("alert")).not.toBeInTheDocument();
    });
  });

  it("deactivates a template without deleting historical usage", async () => {
    const user = userEvent.setup();
    render(<QuotationScoringTemplateListPage />, { wrapper: TestProviders });

    const row = (await screen.findByText("Balanced RFQ Evaluation")).closest("tr");
    expect(row).not.toBeNull();
    expect(within(row as HTMLElement).getByText("0")).toBeInTheDocument();

    await user.click(within(row as HTMLElement).getByRole("button", { name: "Deactivate" }));

    await waitFor(() => {
      expect(within(row as HTMLElement).getByText("Inactive")).toBeInTheDocument();
    });
    expect(within(row as HTMLElement).getByText("0")).toBeInTheDocument();
  });
});

function TestProviders({ children }: { children: ReactNode }) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}
