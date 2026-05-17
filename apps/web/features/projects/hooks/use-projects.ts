"use client";

import { useQuery } from "@tanstack/react-query";
import { listProjects } from "../api/projects-api";
import type { ProjectQuery } from "../types/project-view-model";

export const projectKeys = {
  all: ["projects"] as const,
  lists: () => [...projectKeys.all, "list"] as const,
  list: (query: ProjectQuery) => [...projectKeys.lists(), query] as const,
  detail: (projectId: string) => [...projectKeys.all, "detail", projectId] as const,
  requisitions: (projectId: string) => [...projectKeys.all, "requisitions", projectId] as const,
  activity: (projectId: string) => [...projectKeys.all, "activity", projectId] as const,
};

export function useProjects(query: ProjectQuery = {}) {
  return useQuery({
    queryKey: projectKeys.list(query),
    queryFn: () => listProjects(query),
  });
}
