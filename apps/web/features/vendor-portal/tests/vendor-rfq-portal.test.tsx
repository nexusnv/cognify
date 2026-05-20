import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import {
  expiredVendorPortalToken,
  unavailableVendorPortalToken,
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
    expect(
      screen.getByText(/Quotation submission will be available in a later Cognify workflow/),
    ).toBeInTheDocument();
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
