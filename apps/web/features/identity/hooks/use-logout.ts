import { useMutation, useQueryClient } from "@tanstack/react-query";
import { logout } from "../api/identity-api";

export function useLogout() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ["identity", "logout"],
    mutationFn: logout,
    onSuccess: () => {
      queryClient.clear();
    },
  });
}
