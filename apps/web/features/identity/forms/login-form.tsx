"use client";

import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { loginSchema, type LoginFormValues } from "../schemas/login-schema";
import { useLogin } from "../hooks/use-login";
import { useState } from "react";

export function LoginForm() {
  const [signedIn, setSignedIn] = useState(false);
  const loginMutation = useLogin();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormValues>({
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    resolver: zodResolver(loginSchema) as any,
    defaultValues: { remember: false },
  });

  const onSubmit = async (values: LoginFormValues) => {
    try {
      await loginMutation.mutateAsync(values);
      setSignedIn(true);
    } catch {
      // error handled below
    }
  };

  if (signedIn) {
    return <p className="text-sm text-green-600">Signed in</p>;
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
        {errors.email && (
          <p className="mt-1 text-sm text-red-500">{errors.email.message}</p>
        )}
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
        {errors.password && (
          <p className="mt-1 text-sm text-red-500">{errors.password.message}</p>
        )}
      </div>

      <div className="flex items-center gap-2">
        <input id="remember" type="checkbox" {...register("remember")} />
        <label htmlFor="remember" className="text-sm">
          Remember me
        </label>
      </div>

      <button
        type="submit"
        disabled={loginMutation.isPending}
        className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
      >
        {loginMutation.isPending ? "Signing in..." : "Sign in"}
      </button>

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