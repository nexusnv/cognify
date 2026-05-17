"use client";

import { useRouter } from "next/navigation";
import { Plus } from "lucide-react";
import { useMemo, useState } from "react";
import { Button, NativeSelect } from "@cognify/ui";
import { useDataTableState } from "@/components/data-table/use-data-table-state";
import { useCurrentUser } from "@/features/identity/hooks/use-current-user";
import { useProjects } from "../hooks/use-projects";
import { ProjectsTable } from "../tables/projects-table";
import { canManageProjects } from "../utils/project-access";
import type { ProjectQuery, ProjectStatus } from "../types/project-view-model";

export function ProjectListPage() {
  const router = useRouter();
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState<ProjectStatus | "">("");
  const [department, setDepartment] = useState("");
  const tableState = useDataTableState({ initialSort: { columnId: "name", direction: "asc" } });
  const currentUserQuery = useCurrentUser();
  const canCreateProject = canManageProjects(currentUserQuery.data?.data.activeRole);

  const query = useMemo<ProjectQuery>(
    () => ({ search, status, department }),
    [search, status, department],
  );

  const projectsQuery = useProjects(query);
  const projects = useMemo(() => {
    const rows = projectsQuery.data?.data ?? [];
    const sort = tableState.sort;
    if (!sort) return rows;

    return [...rows].sort((a, b) => {
      const aValue = sort.columnId === "name" ? a.name : "";
      const bValue = sort.columnId === "name" ? b.name : "";
      const result = aValue.localeCompare(bValue);
      return sort.direction === "asc" ? result : -result;
    });
  }, [projectsQuery.data?.data, tableState.sort]);

  const state = projectsQuery.isLoading
    ? "loading"
    : projectsQuery.isError
      ? "error"
      : projects.length === 0
        ? "empty"
        : "idle";

  return (
    <section className="space-y-5">
      <div className="flex flex-col gap-3 border-b pb-4 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Projects</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Track procurement workspaces, ownership, and linked requisition flow.
          </p>
        </div>
        <Button
          type="button"
          disabled={!canCreateProject}
          onClick={() => {
            if (canCreateProject) {
              router.push("/projects/new");
            }
          }}
          className="gap-2"
        >
          <Plus className="h-4 w-4" aria-hidden="true" />
          New project
        </Button>
      </div>

      <div className="grid gap-3 rounded-md border p-3 md:grid-cols-3">
        <label className="space-y-1.5 text-sm font-medium">
          Search
          <input
            className="min-h-11 w-full rounded-md border px-3 text-base font-normal"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
        </label>
        <label className="space-y-1.5 text-sm font-medium">
          Status
          <NativeSelect
            value={status}
            onChange={(event) => setStatus(event.target.value as ProjectStatus | "")}
          >
            <option value="">All</option>
            <option value="draft">Draft</option>
            <option value="active">Active</option>
            <option value="on_hold">On hold</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </NativeSelect>
        </label>
        <label className="space-y-1.5 text-sm font-medium">
          Department
          <input
            className="min-h-11 w-full rounded-md border px-3 text-base font-normal"
            value={department}
            onChange={(event) => setDepartment(event.target.value)}
          />
        </label>
      </div>

      <ProjectsTable
        projects={projects}
        state={state}
        filtered={Boolean(search || status || department)}
        onRetry={() => projectsQuery.refetch()}
        pagination={projectsQuery.data?.meta}
        sort={tableState.sort}
        onSortChange={tableState.setSort}
      />
    </section>
  );
}
