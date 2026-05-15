import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it } from "vitest";
import { http, HttpResponse } from "msw";
import { AppProviders } from "@/components/providers/app-providers";
import { server } from "@/tests/msw/server";
import { multiTenantIdentity } from "@/features/identity/mocks/identity-fixtures";
import { SystemStatusPage } from "../workflows/system-status-page";
import { healthySystemStatus } from "../mocks/system-readiness-fixtures";

describe("SystemStatusPage", () => {
  beforeEach(() => {
    window.localStorage.setItem("cognify.activeTenantId", "tenant-1");
  });

  it("renders readiness checks and demo counts", async () => {
    server.use(
      http.get("/api/system/status", ({ request }) => {
        expect(request.headers.get("x-tenant-id")).toBe("1");
        return HttpResponse.json(healthySystemStatus);
      }),
    );

    render(
      <AppProviders>
        <SystemStatusPage />
      </AppProviders>,
    );

    expect(await screen.findByRole("heading", { name: "System Status" })).toBeInTheDocument();
    expect(screen.getByText("Database")).toBeInTheDocument();
    expect(screen.getByText("Demo dataset")).toBeInTheDocument();
    expect(screen.getByText("Requisitions")).toBeInTheDocument();
    expect(screen.getByText("Vendors")).toBeInTheDocument();
  });

  it("shows an error state when the readiness endpoint fails", async () => {
    server.use(
      http.get("/api/system/status", () => {
        return HttpResponse.json(
          {
            error: {
              code: "server_error",
              message: "Readiness unavailable.",
              details: {},
              requestId: null,
            },
          },
          { status: 500 },
        );
      }),
    );

    render(
      <AppProviders>
        <SystemStatusPage />
      </AppProviders>,
    );

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "System status could not be loaded.",
    );
  });

  it("shows a no active tenant state instead of loading forever", async () => {
    let systemStatusRequested = false;
    server.use(
      http.get("/api/me", () => HttpResponse.json({ data: multiTenantIdentity })),
      http.get("/api/system/status", () => {
        systemStatusRequested = true;
        return HttpResponse.json(healthySystemStatus);
      }),
    );

    render(
      <AppProviders>
        <SystemStatusPage />
      </AppProviders>,
    );

    expect(await screen.findByRole("alert")).toHaveTextContent("No active workspace selected.");
    expect(systemStatusRequested).toBe(false);
  });
});
