"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import {
  createProjectRecord,
  transitionProject,
  updateProjectRecord,
} from "../api/projects-api";
import type { ProjectFormValues } from "../types/project-view-model";
import { projectKeys } from "./use-projects";

export function useCreateProject() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: ProjectFormValues) => createProjectRecord(values),
    onSuccess: (project) => {
      queryClient.invalidateQueries({ queryKey: projectKeys.lists() });
      queryClient.invalidateQueries({ queryKey: projectKeys.detail(project.id) });
    },
  });
}

export function useUpdateProject(projectId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: Partial<ProjectFormValues>) => updateProjectRecord(projectId, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: projectKeys.detail(projectId) });
      queryClient.invalidateQueries({ queryKey: projectKeys.lists() });
      queryClient.invalidateQueries({ queryKey: projectKeys.requisitions(projectId) });
    },
  });
}

export function useProjectStatusAction(projectId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      action,
      reason,
    }: {
      action: "activate" | "hold" | "resume" | "complete" | "cancel";
      reason?: string;
    }) => transitionProject(projectId, action, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: projectKeys.detail(projectId) });
      queryClient.invalidateQueries({ queryKey: projectKeys.lists() });
      queryClient.invalidateQueries({ queryKey: projectKeys.requisitions(projectId) });
    },
  });
}
