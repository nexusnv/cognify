"use client";

import { useQuery } from "@tanstack/react-query";
import { fetchProject } from "../api/projects-api";
import { projectKeys } from "./use-projects";

export function useProject(projectId: string) {
  return useQuery({
    queryKey: projectKeys.detail(projectId),
    queryFn: () => fetchProject(projectId),
    enabled: Boolean(projectId),
  });
}
