import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { ListNotificationsParams } from "@cognify/api-client/schemas";
import {
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from "../api/notifications-api";

export const notificationQueryKey = ["notifications"] as const;

export function useNotifications(params: ListNotificationsParams = { status: "all", limit: 20 }) {
  return useQuery({
    queryKey: [...notificationQueryKey, params],
    queryFn: () => listNotifications(params),
  });
}

export function useUnreadNotifications() {
  return useNotifications({ status: "unread", limit: 20 });
}

export function useMarkNotificationRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: markNotificationRead,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: notificationQueryKey });
    },
  });
}

export function useMarkAllNotificationsRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: markAllNotificationsRead,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: notificationQueryKey });
    },
  });
}
