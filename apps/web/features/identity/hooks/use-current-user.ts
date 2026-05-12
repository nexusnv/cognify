import { useQuery } from "@tanstack/react-query";
import { getCurrentUser } from "../api/identity-api";

export function useCurrentUser() {
  return useQuery({
    queryKey: ["identity", "current-user"],
    queryFn: getCurrentUser,
    retry: false,
  });
}