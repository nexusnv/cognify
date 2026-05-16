import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { RightPanelProvider } from "@/components/right-panel/right-panel-provider";
import { RightPanelRoot } from "@/components/right-panel/right-panel-root";
import { server } from "@/tests/msw/server";
import { multiTenantIdentity } from "@/features/identity/mocks/identity-fixtures";
import { resetIdentityMockState } from "@/features/identity/mocks/identity-handlers";
import { resetProjectMockState } from "../mocks/project-handlers";
import { ProjectCreatePage } from "../workflows/project-create-page";
import { ProjectDetailPage } from "../workflows/project-detail-page";
import { ProjectEditPage } from "../workflows/project-edit-page";
import { ProjectListPage } from "../workflows/project-list-page";

const buyerIdentity = structuredClone(multiTenantIdentity);
buyerIdentity.activeTenant = { id: "2", name: "Northwind Sourcing" };
buyerIdentity.activeRole = "buyer";

function mockCurrentUser(identity = buyerIdentity) {
  server.use(http.get("/api/me", () => HttpResponse.json({ data: identity })));
}

const pushMock = vi.fn();
vi.mock("next/navigation", async (importOriginal) => {
  const actual = await importOriginal<typeof import("next/navigation")>();
  return {
    ...actual,
    useRouter: () => ({
      push: pushMock,
    }),
  };
});

function TestAppProviders({ children }: { children: React.ReactNode }) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return (
    <QueryClientProvider client={queryClient}>
      <RightPanelProvider>
        {children}
        <RightPanelRoot />
      </RightPanelProvider>
    </QueryClientProvider>
  );
}

beforeEach(() => {
  resetIdentityMockState();
  resetProjectMockState();
  window.localStorage.clear();
  pushMock.mockReset();
});

describe("projects workflow", () => {
  it("disables create project for requester roles", async () => {
    render(<ProjectListPage />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("button", { name: "New project" })).toBeDisabled();
  });

  it("lets buyer roles reach the create form and validates required fields", async () => {
    mockCurrentUser();
    const user = userEvent.setup();

    render(<ProjectCreatePage />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Create project" })).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Create project" }));

    expect(
      await screen.findByText("Resolve the highlighted project fields before continuing."),
    ).toBeInTheDocument();
    expect(screen.getAllByText("Project name is required").length).toBeGreaterThan(0);
  });

  it("separates current user fetch failures from project creation permissions", async () => {
    server.use(http.get("/api/me", () => HttpResponse.json({ message: "Unavailable" }, { status: 500 })));

    render(<ProjectCreatePage />, { wrapper: TestAppProviders });

    expect(await screen.findByText("Unable to load access context. Try again.")).toBeInTheDocument();
    expect(screen.queryByText("Your role does not allow project creation.")).not.toBeInTheDocument();
  });

  it("surfaces backend validation and forbidden errors in the project form", async () => {
    mockCurrentUser();
    const user = userEvent.setup();

    server.use(
      http.patch("/api/projects/:projectId", () =>
        HttpResponse.json(
          {
            error: {
              code: "validation_failed",
              message: "Validation failed",
              details: {
                fields: {
                  name: ["The project name is already in use."],
                },
              },
            },
          },
          { status: 422 },
        ),
      ),
    );

    render(<ProjectEditPage projectId="501" />, { wrapper: TestAppProviders });

    expect(await screen.findByDisplayValue("Office refresh")).toBeInTheDocument();

    await user.clear(screen.getByLabelText("Project name"));
    await user.type(screen.getByLabelText("Project name"), "Office refresh");
    await user.click(screen.getByRole("button", { name: "Save project" }));

    expect(screen.getByRole("alert")).toHaveTextContent("Resolve the highlighted project fields");
    expect(screen.getByRole("alert")).toHaveTextContent("The project name is already in use.");
    expect(screen.getByLabelText("Project name")).toHaveAttribute("aria-invalid", "true");

    server.use(
      http.patch("/api/projects/:projectId", () =>
        HttpResponse.json({ message: "Forbidden." }, { status: 403 }),
      ),
    );

    await user.click(screen.getByRole("button", { name: "Save project" }));

    await waitFor(() => {
      expect(screen.getByRole("alert")).toHaveTextContent(
        "You do not have permission to save this project.",
      );
    });
  });

  it("loads the edit workflow", async () => {
    mockCurrentUser();

    render(<ProjectEditPage projectId="501" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Edit project" })).toBeInTheDocument();
    expect(screen.getByDisplayValue("Office refresh")).toBeInTheDocument();
  });

  it("links and unlinks requisitions through the project pipeline", async () => {
    mockCurrentUser();
    const user = userEvent.setup();

    render(<ProjectDetailPage projectId="501" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Office refresh" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Edit" })).toHaveAttribute("href", "/projects/501/edit");

    expect(
      await screen.findByRole("option", {
        name: "REQ-2026-000010 - Returned laptop request",
      }),
    ).toBeInTheDocument();
    expect(screen.queryByRole("option", { name: "REQ-2026-000011 - Withdrawn printer refresh" })).not.toBeInTheDocument();
    expect(screen.queryByRole("option", { name: "REQ-2026-000012 - Cancelled office furniture" })).not.toBeInTheDocument();

    await user.selectOptions(screen.getByLabelText("Link requisition"), "req-changes");
    await user.click(screen.getByRole("button", { name: "Link requisition" }));

    await waitFor(() => {
      expect(screen.getAllByRole("button", { name: "Unlink" })).toHaveLength(3);
    });

    const unlinkButtons = screen.getAllByRole("button", { name: "Unlink" });
    await user.click(unlinkButtons[unlinkButtons.length - 1]!);

    await waitFor(() => {
      expect(screen.getAllByRole("button", { name: "Unlink" })).toHaveLength(2);
      expect(screen.queryByText("Returned laptop request")).not.toBeInTheDocument();
    });
  });
});
