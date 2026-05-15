"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  addProjectRequisitionLink,
  fetchProjectActivity,
  fetchProjectRequisitions,
  removeProjectRequisitionLink,
} from "../api/projects-api";
import { projectKeys } from "./use-projects";

export function useProjectRequisitions(projectId: string) {
  return useQuery({
    queryKey: projectKeys.requisitions(projectId),
    queryFn: () => fetchProjectRequisitions(projectId),
    enabled: Boolean(projectId),
  });
}

export function useProjectActivity(projectId: string) {
  return useQuery({
    queryKey: projectKeys.activity(projectId),
    queryFn: () => fetchProjectActivity(projectId),
    enabled: Boolean(projectId),
  });
}

export function useLinkProjectRequisition(projectId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (requisitionId: string) => addProjectRequisitionLink(projectId, requisitionId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: projectKeys.requisitions(projectId) });
      queryClient.invalidateQueries({ queryKey: projectKeys.detail(projectId) });
      queryClient.invalidateQueries({ queryKey: projectKeys.lists() });
    },
  });
}

export function useUnlinkProjectRequisition(projectId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (requisitionId: string) => removeProjectRequisitionLink(projectId, requisitionId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: projectKeys.requisitions(projectId) });
      queryClient.invalidateQueries({ queryKey: projectKeys.detail(projectId) });
      queryClient.invalidateQueries({ queryKey: projectKeys.lists() });
    },
  });
}
