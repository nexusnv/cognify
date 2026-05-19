"use client";

import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod/v4";
import { loginSchema, type LoginFormValues } from "../schemas/login-schema";
import { useLogin } from "../hooks/use-login";
import { useState } from "react";
import { requestPasswordReset } from "../api/identity-api";

const resetPasswordSchema = z.object({
  email: z.string().email("Enter a valid email address."),
});

type ResetPasswordValues = z.infer<typeof resetPasswordSchema>;

export function LoginForm({ onAuthenticated }: { onAuthenticated?: () => void }) {
  const [resetMode, setResetMode] = useState(false);
  const [resetSent, setResetSent] = useState(false);
  const [resetError, setResetError] = useState<string | null>(null);
  const [resetPending, setResetPending] = useState(false);
  const loginMutation = useLogin();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { remember: false },
  });
  const {
    register: registerReset,
    handleSubmit: handleResetSubmit,
    formState: { errors: resetErrors },
  } = useForm<ResetPasswordValues>({
    resolver: zodResolver(resetPasswordSchema),
  });

  const onSubmit = async (values: LoginFormValues) => {
    try {
      await loginMutation.mutateAsync(values);
      onAuthenticated?.();
    } catch {
      // error handled below
    }
  };

  const onResetSubmit = async ({ email }: ResetPasswordValues) => {
    setResetSent(false);
    setResetError(null);
    setResetPending(true);
    try {
      await requestPasswordReset(email);
      setResetSent(true);
    } catch {
      setResetError("We could not request a password reset. Try again.");
    } finally {
      setResetPending(false);
    }
  };

  if (resetMode) {
    return (
      <form onSubmit={handleResetSubmit(onResetSubmit)} className="space-y-4">
        <div>
          <label htmlFor="email" className="block text-sm font-medium">
            Email
          </label>
          <input
            id="email"
            type="email"
            {...registerReset("email")}
            className="mt-1 block w-full rounded-md border px-3 py-2 text-sm"
          />
          {resetErrors.email && (
            <p className="mt-1 text-sm text-red-500">{resetErrors.email.message}</p>
          )}
        </div>

        <div className="flex items-center gap-3">
          <button
            type="submit"
            disabled={resetPending}
            className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
          >
            {resetPending ? "Sending..." : "Send reset instructions"}
          </button>
          <button
            type="button"
            className="text-sm text-muted-foreground underline"
            onClick={() => {
              setResetSent(false);
              setResetMode(false);
              setResetError(null);
            }}
          >
            Back to sign in
          </button>
        </div>

        {resetSent && <p className="text-sm text-green-600">Password reset instructions sent.</p>}
        {resetError && <p className="text-sm text-red-500">{resetError}</p>}
      </form>
    );
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <div>
        <label htmlFor="email" className="block text-sm font-medium">
          Email
        </label>
        <input
          id="email"
          type="email"
          {...register("email")}
          className="mt-1 block w-full rounded-md border px-3 py-2 text-sm"
        />
        {errors.email && <p className="mt-1 text-sm text-red-500">{errors.email.message}</p>}
      </div>

      <div>
        <label htmlFor="password" className="block text-sm font-medium">
          Password
        </label>
        <input
          id="password"
          type="password"
          {...register("password")}
          className="mt-1 block w-full rounded-md border px-3 py-2 text-sm"
        />
        {errors.password && <p className="mt-1 text-sm text-red-500">{errors.password.message}</p>}
      </div>

      <div className="flex items-center gap-2">
        <input id="remember" type="checkbox" {...register("remember")} />
        <label htmlFor="remember" className="text-sm">
          Remember me
        </label>
      </div>

      <div className="flex items-center gap-3">
        <button
          type="submit"
          disabled={loginMutation.isPending}
          className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
        >
          {loginMutation.isPending ? "Signing in..." : "Sign in"}
        </button>
        <button
          type="button"
          className="text-sm text-muted-foreground underline"
          onClick={() => {
            setResetSent(false);
            setResetMode(true);
            setResetError(null);
          }}
        >
          Forgot password?
        </button>
      </div>

      {loginMutation.error && (
        <p className="text-sm text-red-500">
          {loginMutation.error instanceof Error
            ? loginMutation.error.message
            : "Invalid credentials"}
        </p>
      )}
    </form>
  );
}
