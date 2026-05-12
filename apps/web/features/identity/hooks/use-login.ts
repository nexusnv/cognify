import { useMutation, useQueryClient } from "@tanstack/react-query";
import { login } from "../api/identity-api";
import type { LoginFormValues } from "../schemas/login-schema";

export function useLogin() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ["identity", "login"],
    mutationFn: (values: LoginFormValues) => login(values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["identity", "current-user"] });
    },
  });
}