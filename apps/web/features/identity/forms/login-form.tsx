"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Alert,
  AlertDescription,
  Button,
  Checkbox,
  Field,
  FieldError,
  FieldLabel,
  Input,
} from "@cognify/ui";
import { Eye, EyeOff } from "lucide-react";
import { useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { z } from "zod";
import { FormField } from "@/components/forms/form-field";
import { requestPasswordReset } from "../api/identity-api";
import { useLogin } from "../hooks/use-login";
import { loginSchema, type LoginFormValues } from "../schemas/login-schema";

const resetPasswordSchema = z.object({
  email: z.string().email("Enter a valid email address."),
});

type ResetPasswordValues = z.infer<typeof resetPasswordSchema>;

export function LoginForm({ onAuthenticated }: { onAuthenticated?: () => void }) {
  const [resetMode, setResetMode] = useState(false);
  const [resetSent, setResetSent] = useState(false);
  const [resetError, setResetError] = useState<string | null>(null);
  const [resetPending, setResetPending] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const loginMutation = useLogin();

  const {
    register,
    control,
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
      <form onSubmit={handleResetSubmit(onResetSubmit)} className="grid gap-5">
        <FormField
          htmlFor="email"
          label="Email"
          error={resetErrors.email?.message}
          required
        >
          <Input {...registerReset("email")} type="email" autoComplete="email" />
        </FormField>

        <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
          <Button type="submit" disabled={resetPending}>
            {resetPending ? "Sending..." : "Send reset instructions"}
          </Button>
          <Button
            type="button"
            variant="ghost"
            onClick={() => {
              setResetSent(false);
              setResetMode(false);
              setResetError(null);
            }}
          >
            Back to sign in
          </Button>
        </div>

        {resetSent && (
          <Alert>
            <AlertDescription>Password reset instructions sent.</AlertDescription>
          </Alert>
        )}
        {resetError && (
          <Alert variant="destructive">
            <AlertDescription>{resetError}</AlertDescription>
          </Alert>
        )}
      </form>
    );
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="grid gap-5">
      <FormField htmlFor="email" label="Email" error={errors.email?.message} required>
        <Input {...register("email")} type="email" autoComplete="email" />
      </FormField>

      <Field className="grid gap-2">
        <div className="flex items-center">
          <FieldLabel htmlFor="password">Password</FieldLabel>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="ml-auto h-auto px-0 py-0 text-muted-foreground hover:bg-transparent hover:text-foreground"
            onClick={() => {
              setResetSent(false);
              setResetMode(true);
              setResetError(null);
            }}
          >
            Forgot password?
          </Button>
        </div>
        <div className="relative">
          <Input
            id="password"
            type={showPassword ? "text" : "password"}
            {...register("password")}
            autoComplete="current-password"
            aria-invalid={Boolean(errors.password)}
            aria-describedby={errors.password ? "login-password-error" : undefined}
            className="pr-10"
          />
          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="absolute right-0 top-0 h-10 w-10 text-muted-foreground hover:bg-transparent hover:text-foreground"
            aria-label={showPassword ? "Hide password" : "Show password"}
            onClick={() => setShowPassword((value) => !value)}
          >
            {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
          </Button>
        </div>
        <FieldError id="login-password-error">{errors.password?.message}</FieldError>
      </Field>

      <Controller
        control={control}
        name="remember"
        render={({ field }) => (
          <Field orientation="horizontal" className="items-center gap-2">
            <Checkbox
              id="remember"
              checked={field.value}
              onCheckedChange={(checked) => field.onChange(Boolean(checked))}
            />
            <FieldLabel htmlFor="remember" className="text-sm font-normal">
              Remember me
            </FieldLabel>
          </Field>
        )}
      />

      <div className="grid gap-3">
        <Button type="submit" disabled={loginMutation.isPending} className="w-full">
          {loginMutation.isPending ? "Signing in..." : "Sign in"}
        </Button>
      </div>

      {loginMutation.error && (
        <Alert variant="destructive">
          <AlertDescription>
            {loginMutation.error instanceof Error
              ? loginMutation.error.message
              : "Invalid credentials"}
          </AlertDescription>
        </Alert>
      )}
    </form>
  );
}
