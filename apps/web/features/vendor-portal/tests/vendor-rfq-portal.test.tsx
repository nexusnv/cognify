import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it } from "vitest";
import {
  expiredVendorPortalToken,
  unavailableVendorPortalToken,
  resetVendorPortalMockState,
  validVendorPortalToken,
} from "../mocks/vendor-portal-fixtures";
import { VendorRfqInvitationPage } from "../workflows/vendor-rfq-invitation-page";

function TestProviders({ children }: { children: React.ReactNode }) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}

describe("vendor RFQ portal", () => {
  beforeEach(() => {
    resetVendorPortalMockState();
  });

  afterEach(() => {
    resetVendorPortalMockState();
  });

  it("renders the invited RFQ package for a valid token", async () => {
    render(<VendorRfqInvitationPage token={validVendorPortalToken} />, { wrapper: TestProviders });

    expect(
      await screen.findByRole(
        "heading",
        { name: "Field laptop refresh RFQ" },
        { timeout: 5_000 },
      ),
    ).toBeInTheDocument();
    expect(screen.getByText("Northwind Traders")).toBeInTheDocument();
    expect(screen.getByText("Supply and deliver laptops for field teams.")).toBeInTheDocument();
    expect(screen.getByText("Developer laptop")).toBeInTheDocument();
    expect(screen.getByText("Quotation PDF")).toBeInTheDocument();
    expect(screen.getByLabelText("Quotation file")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Upload quotation" })).toBeInTheDocument();
    expect(
      screen.queryByText(/Quotation submission will be available in a later Cognify workflow/),
    ).not.toBeInTheDocument();
  });

  it("uploads a quotation file and shows the received state", async () => {
    const user = userEvent.setup();

    render(<VendorRfqInvitationPage token={validVendorPortalToken} />, { wrapper: TestProviders });

    const fileInput = await screen.findByLabelText("Quotation file");
    const file = new File(["quotation content"], "vendor-quotation.pdf", {
      type: "application/pdf",
    });

    await user.upload(fileInput, file);
    await user.click(screen.getByRole("button", { name: "Upload quotation" }));

    expect(await screen.findByText("Quotation received")).toBeInTheDocument();
    expect(await screen.findByText("vendor-quotation.pdf")).toBeInTheDocument();
  });

  it("shows a safe invalid link state", async () => {
    render(<VendorRfqInvitationPage token="invalid-token" />, { wrapper: TestProviders });

    expect(await screen.findByRole("alert")).toHaveTextContent("Invitation link not found");
    expect(screen.queryByText("Field laptop refresh RFQ")).not.toBeInTheDocument();
  });

  it("shows a safe expired link state", async () => {
    render(<VendorRfqInvitationPage token={expiredVendorPortalToken} />, { wrapper: TestProviders });

    expect(await screen.findByRole("alert")).toHaveTextContent("Invitation link unavailable");
    expect(screen.queryByText("Field laptop refresh RFQ")).not.toBeInTheDocument();
  });

  it("shows a safe unavailable invitation state", async () => {
    render(<VendorRfqInvitationPage token={unavailableVendorPortalToken} />, { wrapper: TestProviders });

    expect(await screen.findByRole("alert")).toHaveTextContent("Invitation link unavailable");
    expect(screen.queryByText("Field laptop refresh RFQ")).not.toBeInTheDocument();
  });
});
