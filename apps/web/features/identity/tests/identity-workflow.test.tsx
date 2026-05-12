import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { describe, expect, it, beforeEach } from "vitest";
import { server } from "../../../tests/msw/server";
import { LoginPage } from "../workflows/login-page";
import { SessionGate } from "../workflows/session-gate";
import { AccountSettingsPage } from "../workflows/account-settings-page";
import { resetIdentityMockState } from "../mocks/identity-handlers";

function renderWithQuery(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

describe("identity workflow", () => {
  it("signs in and loads current identity context", async () => {
    const user = userEvent.setup();

    renderWithQuery(<LoginPage />);

    await user.type(screen.getByLabelText("Email"), "test@example.com");
    await user.type(screen.getByLabelText("Password"), "password123");
    await user.click(screen.getByRole("button", { name: "Sign in" }));

    expect(await screen.findByText("Signed in")).toBeInTheDocument();
  });

  beforeEach(() => {
    resetIdentityMockState();
  });

  it("requires tenant selection for a multi-tenant identity", async () => {
    // Use a mutable store for the multi-tenant identity so the POST handler
    // can update the active tenant and GET will return the updated state.
    let identity = {
      user: {
        id: "1",
        name: "Test User",
        email: "test@example.com",
        avatarUrl: null,
        timezone: "Asia/Kuala_Lumpur",
        locale: "en",
        theme: "system",
      },
      tenants: [
        { id: "1", name: "Acme Procurement", role: "requester" },
        { id: "2", name: "Northwind Sourcing", role: "buyer" },
      ],
      activeTenant: null as { id: string; name: string } | null,
      activeRole: null as string | null,
      permissions: {
        canCreateRequisition: false,
        canViewSubmittedRequisitions: false,
        canUpdateOwnDraftRequisition: false,
        canSubmitOwnDraftRequisition: false,
        canAccessAdmin: false,
      },
    };

    server.use(
      http.get("/api/me", () => {
        return HttpResponse.json({ data: identity });
      }),
      http.post("/api/tenants/current", async ({ request }) => {
        const body = (await request.json()) as { tenantId?: string };
        const membership = identity.tenants.find((t) => t.id === body.tenantId);
        if (membership) {
          identity = {
            ...identity,
            activeTenant: { id: membership.id, name: membership.name },
            activeRole: membership.role,
          };
        }
        return HttpResponse.json({ data: identity });
      }),
    );

    const user = userEvent.setup();

    renderWithQuery(
      <SessionGate>
        <div>Workspace ready</div>
      </SessionGate>,
    );

    expect(await screen.findByRole("heading", { name: "Choose workspace" })).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Northwind Sourcing" }));

    expect(await screen.findByText("Workspace ready")).toBeInTheDocument();
  });

  it("updates profile preferences through the account settings workflow", async () => {
    const user = userEvent.setup();

    renderWithQuery(<AccountSettingsPage />);

    // Wait for the profile data to load
    const nameInput = await screen.findByLabelText("Name");
    await user.clear(nameInput);
    await user.type(nameInput, "Taylor Buyer");
    await user.selectOptions(screen.getByLabelText("Theme"), "dark");
    await user.click(screen.getByRole("button", { name: "Save profile" }));

    expect(await screen.findByText("Profile saved")).toBeInTheDocument();
    expect(screen.getByDisplayValue("Taylor Buyer")).toBeInTheDocument();
  });
});