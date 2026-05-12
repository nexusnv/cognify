import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { describe, expect, it, beforeEach } from "vitest";
import { server } from "../../../tests/msw/server";
import { LoginPage } from "../workflows/login-page";
import { SessionGate } from "../workflows/session-gate";
import { AccountSettingsPage } from "../workflows/account-settings-page";
import { resetIdentityMockState } from "../mocks/identity-handlers";
import { multiTenantIdentity } from "../mocks/identity-fixtures";
import type { CurrentUserContext } from "../types/identity-view-model";
import { getStoredActiveTenantId, setCurrentTenant } from "../api/identity-api";

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
    window.localStorage.clear();
  });

  it("requires tenant selection for a multi-tenant identity", async () => {
    // Use a mutable store initialized from the fixture so the POST handler
    // can update the active tenant and GET will return the updated state.
    let identity: CurrentUserContext = structuredClone(multiTenantIdentity);

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

  it("does not store active tenant until the API validates membership", async () => {
    server.use(
      http.post("/api/tenants/current", () => {
        return HttpResponse.json({ message: "Tenant membership is required." }, { status: 403 });
      }),
    );

    await expect(setCurrentTenant("999")).rejects.toMatchObject({
      message: "Tenant membership is required.",
    });

    expect(getStoredActiveTenantId()).toBeNull();
  });
});
