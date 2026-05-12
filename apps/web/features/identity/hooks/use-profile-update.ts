import { useMutation, useQueryClient } from "@tanstack/react-query";
import { updateCurrentUserProfile } from "../api/identity-api";
import type { ProfileFormValues } from "../schemas/profile-schema";

export function useProfileUpdate() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ["identity", "profile"],
    mutationFn: (values: ProfileFormValues) => updateCurrentUserProfile(values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["identity", "current-user"] });
    },
  });
}