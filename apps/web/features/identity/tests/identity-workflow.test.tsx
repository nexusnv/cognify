import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { describe, expect, it, beforeEach, vi } from "vitest";
import { ThemeProvider } from "@/components/providers/theme-provider";
import { ThemeToggle } from "@/components/shell/theme-toggle";
import { server } from "../../../tests/msw/server";
import { LoginPage } from "../workflows/login-page";
import { SessionGate } from "../workflows/session-gate";
import { AccountSettingsPage } from "../workflows/account-settings-page";
import { resetIdentityMockState } from "../mocks/identity-handlers";
import { multiTenantIdentity } from "../mocks/identity-fixtures";
import { defaultNotificationPreferences } from "../schemas/profile-schema";
import type { CurrentUserContext } from "../types/identity-view-model";
import { getStoredActiveTenantId, logout, setCurrentTenant } from "../api/identity-api";

const router = {
  push: vi.fn(),
  replace: vi.fn(),
  refresh: vi.fn(),
  back: vi.fn(),
  forward: vi.fn(),
  prefetch: vi.fn(),
};

let searchParams = new URLSearchParams();
let pathname = "/dashboard";

if (typeof window !== "undefined" && !window.ResizeObserver) {
  class ResizeObserver {
    observe() {}
    unobserve() {}
    disconnect() {}
  }

  window.ResizeObserver = ResizeObserver as unknown as typeof window.ResizeObserver;
  globalThis.ResizeObserver = ResizeObserver as unknown as typeof globalThis.ResizeObserver;
}

if (typeof window !== "undefined" && !Element.prototype.scrollIntoView) {
  Element.prototype.scrollIntoView = vi.fn();
}

if (typeof window !== "undefined" && !Element.prototype.hasPointerCapture) {
  Element.prototype.hasPointerCapture = () => false;
}

if (typeof window !== "undefined" && !Element.prototype.setPointerCapture) {
  Element.prototype.setPointerCapture = () => undefined;
}

if (typeof window !== "undefined" && !Element.prototype.releasePointerCapture) {
  Element.prototype.releasePointerCapture = () => undefined;
}

vi.mock("next-themes", async () => {
  const React = await import("react");
  const ThemeContext = React.createContext<{
    resolvedTheme: string;
    setTheme: (theme: string) => void;
  }>({
    resolvedTheme: "light",
    setTheme: () => undefined,
  });

  function MockThemeProvider({ children }: { children: React.ReactNode }) {
    const [theme, setThemeState] = React.useState("light");

    React.useEffect(() => {
      document.documentElement.classList.remove("light", "dark");
      document.documentElement.classList.add(theme);
      document.documentElement.style.colorScheme = theme;
      window.localStorage.setItem("cognify-ui-theme", theme);
    }, [theme]);

    return (
      <ThemeContext.Provider
        value={{
          resolvedTheme: theme,
          setTheme: setThemeState,
        }}
      >
        {children}
      </ThemeContext.Provider>
    );
  }

  return {
    ThemeProvider: MockThemeProvider,
    useTheme: () => React.useContext(ThemeContext),
  };
});

vi.mock("next/navigation", () => ({
  usePathname: () => pathname,
  useRouter: () => router,
  useSearchParams: () => searchParams,
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

describe("identity workflow", () => {
  it("renders login fields and primary actions for workspace sign-in", () => {
    renderWithQuery(<LoginPage />);

    expect(
      screen.getByRole("heading", { name: "Sign in to your procurement workspace" }),
    ).toBeInTheDocument();
    expect(screen.getByRole("textbox", { name: "Email" })).toBeInTheDocument();
    expect(screen.getByLabelText("Password")).toBeInTheDocument();
    expect(screen.getByRole("checkbox", { name: "Remember me" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Sign in" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Forgot password?" })).toBeInTheDocument();
  });

  it("signs in and loads current identity context", async () => {
    let csrfRequested = false;
    let loginXsrfHeader: string | null = null;
    let loginBody: unknown;
    server.use(
      http.get("/sanctum/csrf-cookie", () => {
        csrfRequested = true;
        document.cookie = "XSRF-TOKEN=dev-token";

        return new HttpResponse(null, { status: 204 });
      }),
      http.post("/api/auth/login", async ({ request }) => {
        loginXsrfHeader = request.headers.get("x-xsrf-token");
        loginBody = await request.json();

        return new HttpResponse(null, { status: 204 });
      }),
    );
    const user = userEvent.setup();
    searchParams = new URLSearchParams({ next: "/projects/1" });

    renderWithQuery(<LoginPage />);

    await user.type(screen.getByLabelText("Email"), "test@example.com");
    await user.type(screen.getByLabelText("Password"), "password123");
    await user.click(screen.getByLabelText("Remember me"));
    await user.click(screen.getByRole("button", { name: "Sign in" }));

    expect(router.replace).toHaveBeenCalledWith("/projects/1");
    expect(csrfRequested).toBe(true);
    expect(loginXsrfHeader).toBe("dev-token");
    expect(loginBody).toEqual({
      email: "test@example.com",
      password: "password123",
      remember: true,
    });
  });

  it("shows failed login feedback without bypassing the login hook", async () => {
    server.use(
      http.get("/sanctum/csrf-cookie", () => {
        document.cookie = "XSRF-TOKEN=dev-token";
        return new HttpResponse(null, { status: 204 });
      }),
      http.post("/api/auth/login", () => {
        return HttpResponse.json({ message: "Invalid credentials" }, { status: 422 });
      }),
    );
    const user = userEvent.setup();

    renderWithQuery(<LoginPage />);

    await user.type(screen.getByRole("textbox", { name: "Email" }), "test@example.com");
    await user.type(screen.getByLabelText("Password"), "wrong-password");
    await user.click(screen.getByRole("button", { name: "Sign in" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("Invalid credentials");
    expect(router.replace).not.toHaveBeenCalled();
  });

  beforeEach(() => {
    resetIdentityMockState();
    window.localStorage.clear();
    router.replace.mockReset();
    searchParams = new URLSearchParams();
    pathname = "/dashboard";
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

  it("updates profile theme and notification preferences without changing shortcut theme storage", async () => {
    let submittedBody: unknown;
    server.use(
      http.patch("/api/me/profile", async ({ request }) => {
        submittedBody = await request.json();
        return HttpResponse.json({
          data: {
            ...multiTenantIdentity,
            user: {
              ...multiTenantIdentity.user,
              name: "Taylor Buyer",
              theme: "dark",
              notificationPreferences: {
                ...defaultNotificationPreferences,
                "attachment.uploaded": { inApp: false },
              },
            },
          },
        });
      }),
    );
    const user = userEvent.setup();

    renderWithQuery(
      <ThemeProvider>
        <div>
          <ThemeToggle />
          <AccountSettingsPage />
        </div>
      </ThemeProvider>,
    );

    window.localStorage.removeItem("cognify-ui-theme");
    await user.click(screen.getByRole("button", { name: "Switch to dark mode" }));
    await waitFor(() => {
      expect(window.localStorage.getItem("cognify-ui-theme")).toBe("dark");
    });
    const nameInput = await screen.findByLabelText("Name");
    await user.clear(nameInput);
    await user.type(nameInput, "Taylor Buyer");
    const themeSelect = screen.getByRole("combobox", { name: "Theme" });
    expect(themeSelect).toHaveAttribute("aria-describedby", "profile-theme-description");
    await user.click(themeSelect);
    await user.click(await screen.findByRole("option", { name: "Dark" }));
    await user.click(screen.getByRole("switch", { name: "Evidence uploaded" }));
    await user.click(screen.getByRole("button", { name: "Save profile" }));

    await waitFor(() => {
      expect(submittedBody).toEqual(
        expect.objectContaining({
          name: "Taylor Buyer",
          theme: "dark",
        }),
      );
    });
    expect(
      (submittedBody as { notificationPreferences?: unknown }).notificationPreferences,
    ).toEqual({
      ...defaultNotificationPreferences,
      "attachment.uploaded": { inApp: false },
    });
    expect(window.localStorage.getItem("cognify-ui-theme")).toBe("dark");
  });

  it("clears stale profile save errors once the form becomes dirty again", async () => {
    server.use(
      http.patch("/api/me/profile", () => {
        return HttpResponse.json({ message: "Save failed" }, { status: 500 });
      }),
    );
    const user = userEvent.setup();

    renderWithQuery(<AccountSettingsPage />);

    const nameInput = await screen.findByLabelText("Name");
    await user.clear(nameInput);
    await user.type(nameInput, "Taylor Buyer");
    await user.click(screen.getByRole("button", { name: "Save profile" }));

    expect(await screen.findByText("Failed to save profile")).toBeInTheDocument();

    await user.clear(nameInput);
    await user.type(nameInput, "Taylor Buyer II");

    await waitFor(() => {
      expect(screen.queryByText("Failed to save profile")).not.toBeInTheDocument();
    });
  });

  it("shows an account settings error when profile loading fails", async () => {
    server.use(
      http.get("/api/me", () => {
        return HttpResponse.json({ message: "Service unavailable." }, { status: 503 });
      }),
    );

    renderWithQuery(<AccountSettingsPage />);

    expect(await screen.findByText("Failed to load profile.")).toBeInTheDocument();
  });

  it("does not treat transient session failures as sign-in requirements", async () => {
    server.use(
      http.get("/api/me", () => {
        return HttpResponse.json({ message: "Service unavailable." }, { status: 503 });
      }),
    );

    renderWithQuery(
      <SessionGate>
        <div>Workspace ready</div>
      </SessionGate>,
    );

    expect(await screen.findByText("Workspace unavailable.")).toBeInTheDocument();
    expect(screen.queryByText("Sign in required")).not.toBeInTheDocument();
  });

  it("links unauthenticated users back to the protected page after login", async () => {
    pathname = "/projects/1";
    server.use(
      http.get("/api/me", () => {
        return HttpResponse.json({ message: "Unauthenticated." }, { status: 401 });
      }),
    );

    renderWithQuery(
      <SessionGate>
        <div>Workspace ready</div>
      </SessionGate>,
    );

    const loginLink = await screen.findByRole("link", { name: "sign in" });
    expect(loginLink).toHaveAttribute("href", "/login?next=%2Fprojects%2F1");
    expect(router.replace).toHaveBeenCalledWith("/login?next=%2Fprojects%2F1");
  });

  it("includes the current query string in the sign-in next path", async () => {
    pathname = "/projects/1";
    searchParams = new URLSearchParams({ tab: "activity", view: "compact" });
    server.use(
      http.get("/api/me", () => {
        return HttpResponse.json({ message: "Unauthenticated." }, { status: 401 });
      }),
    );

    renderWithQuery(
      <SessionGate>
        <div>Workspace ready</div>
      </SessionGate>,
    );

    const loginLink = await screen.findByRole("link", { name: "sign in" });
    expect(loginLink).toHaveAttribute(
      "href",
      "/login?next=%2Fprojects%2F1%3Ftab%3Dactivity%26view%3Dcompact",
    );
    expect(router.replace).toHaveBeenCalledWith(
      "/login?next=%2Fprojects%2F1%3Ftab%3Dactivity%26view%3Dcompact",
    );
  });

  it("lets a multi-tenant user choose a workspace when current user has no active tenant", async () => {
    server.use(
      http.get("/api/me", () => {
        return HttpResponse.json({ data: multiTenantIdentity });
      }),
    );

    renderWithQuery(
      <SessionGate>
        <div>Workspace ready</div>
      </SessionGate>,
    );

    expect(await screen.findByRole("heading", { name: "Choose workspace" })).toBeInTheDocument();
    expect(screen.queryByText("Workspace unavailable.")).not.toBeInTheDocument();
  });

  it("requests password reset instructions from the login screen", async () => {
    let resetBody: unknown;
    server.use(
      http.post("/api/auth/forgot-password", async ({ request }) => {
        resetBody = await request.json();
        return new HttpResponse(null, { status: 204 });
      }),
    );
    const user = userEvent.setup();

    renderWithQuery(<LoginPage />);

    await user.click(screen.getByRole("button", { name: "Forgot password?" }));
    await user.type(screen.getByLabelText("Email"), "test@example.com");
    await user.click(screen.getByRole("button", { name: "Send reset instructions" }));

    expect(await screen.findByText("Password reset instructions sent.")).toBeInTheDocument();
    expect(resetBody).toEqual({ email: "test@example.com" });
  });

  it("clears stale password reset success before retrying", async () => {
    let resetAttempts = 0;
    server.use(
      http.post("/api/auth/forgot-password", () => {
        resetAttempts += 1;

        return resetAttempts === 1
          ? new HttpResponse(null, { status: 204 })
          : HttpResponse.json({ message: "Reset failed." }, { status: 500 });
      }),
    );
    const user = userEvent.setup();

    renderWithQuery(<LoginPage />);

    await user.click(screen.getByRole("button", { name: "Forgot password?" }));
    await user.type(screen.getByLabelText("Email"), "test@example.com");
    await user.click(screen.getByRole("button", { name: "Send reset instructions" }));

    expect(await screen.findByText("Password reset instructions sent.")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Send reset instructions" }));

    expect(
      await screen.findByText("We could not request a password reset. Try again."),
    ).toBeInTheDocument();
    expect(screen.queryByText("Password reset instructions sent.")).not.toBeInTheDocument();
  });

  it("clears the stored active tenant when logging out", async () => {
    window.localStorage.setItem("cognify.activeTenantId", "1");

    await logout();

    expect(getStoredActiveTenantId()).toBeNull();
  });

  it("does not store active tenant until the API validates membership", async () => {
    server.use(
      http.post("/api/tenants/current", () => {
        return HttpResponse.json({ message: "Tenant membership is required." }, { status: 403 });
      }),
    );

    await expect(setCurrentTenant("999")).rejects.toMatchObject({
      status: 403,
      data: { message: "Tenant membership is required." },
    });

    expect(getStoredActiveTenantId()).toBeNull();
  });
});
