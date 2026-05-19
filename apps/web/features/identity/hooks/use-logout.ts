import { useMutation, useQueryClient } from "@tanstack/react-query";
import { logout } from "../api/identity-api";

export function useLogout() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ["identity", "logout"],
    mutationFn: logout,
    onSuccess: () => {
      queryClient.removeQueries({
        predicate: (query) => {
          const scope = query.queryKey[0];
          return (
            scope === "identity" ||
            scope === "requisitions" ||
            scope === "requisition" ||
            scope === "projects" ||
            scope === "project" ||
            scope === "approval" ||
            scope === "approvals" ||
            scope === "notifications" ||
            scope === "system"
          );
        },
      });
    },
  });
}
