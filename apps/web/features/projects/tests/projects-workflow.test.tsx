import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { describe, expect, it, vi } from "vitest";
import { RightPanelProvider } from "@/components/right-panel/right-panel-provider";
import { RightPanelRoot } from "@/components/right-panel/right-panel-root";
import { server } from "@/tests/msw/server";
import { projectResponseFixture } from "../mocks/project-fixtures";
import { ProjectCreatePage } from "../workflows/project-create-page";
import { ProjectDetailPage } from "../workflows/project-detail-page";
import { ProjectListPage } from "../workflows/project-list-page";

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

describe("projects workflow", () => {
  it("list displays Office refresh", async () => {
    render(<ProjectListPage />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Projects" })).toBeInTheDocument();
    expect(await screen.findAllByText("Office refresh")).not.toHaveLength(0);
  });

  it("shows empty state when no projects are returned", async () => {
    server.use(
      http.get("/api/projects", () =>
        HttpResponse.json({
          data: [],
          meta: { currentPage: 1, perPage: 15, total: 0, lastPage: 1 },
        }),
      ),
    );

    render(<ProjectListPage />, { wrapper: TestAppProviders });

    expect(await screen.findByText("No projects yet")).toBeInTheDocument();
  });

  it("create form validates required name", async () => {
    const user = userEvent.setup();

    render(<ProjectCreatePage />, { wrapper: TestAppProviders });

    await user.click(screen.getByRole("button", { name: "Create project" }));

    expect(
      await screen.findByText("Resolve the highlighted project fields before continuing."),
    ).toBeInTheDocument();
    expect(screen.getAllByText("Project name is required").length).toBeGreaterThan(0);
  });

  it("successful create calls generated-shaped handler", async () => {
    const user = userEvent.setup();
    let requestPayload: unknown = null;

    server.use(
      http.post("/api/projects", async ({ request }) => {
        requestPayload = await request.json();
        return HttpResponse.json(projectResponseFixture, { status: 201 });
      }),
    );

    render(<ProjectCreatePage />, { wrapper: TestAppProviders });

    await user.type(screen.getByLabelText("Project name"), "HQ relocation");
    await user.selectOptions(screen.getByLabelText("Owner"), "1");
    await user.type(screen.getByLabelText("Budget"), "90000");
    await user.type(screen.getByLabelText("Currency"), "MYR");

    await user.click(screen.getByRole("button", { name: "Create project" }));

    expect(requestPayload).toMatchObject({
      name: "HQ relocation",
      ownerId: "1",
      budgetAmount: "90000",
      currency: "MYR",
    });
  });

  it("renders project workspace summary and placeholders", async () => {
    render(<ProjectDetailPage projectId="501" />, { wrapper: TestAppProviders });

    expect(await screen.findByRole("heading", { name: "Office refresh" })).toBeInTheDocument();
    expect(screen.getByText("PRJ-2026-000501")).toBeInTheDocument();
    expect(screen.getByText("Budget summary")).toBeInTheDocument();
    expect(screen.getByText("Requisition pipeline")).toBeInTheDocument();
    expect(screen.getByText("Approval routing is not active for projects yet.")).toBeInTheDocument();
    expect(screen.getByText("Project risks are reserved for a later governance slice.")).toBeInTheDocument();
    expect(
      screen.getByText("Award records will appear here after award workflows are implemented."),
    ).toBeInTheDocument();
  });
});
