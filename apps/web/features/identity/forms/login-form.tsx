"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import {
  Alert,
  AlertDescription,
  Button,
  Checkbox,
  Field,
  FieldContent,
  FieldDescription,
  FieldError,
  FieldLabel,
  Form,
  Input,
} from "@cognify/ui";
import { Eye, EyeOff } from "lucide-react";
import { useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { z } from "zod";
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
      <Form onSubmit={handleResetSubmit(onResetSubmit)} className="grid gap-5">
        <Field data-invalid={Boolean(resetErrors.email)}>
          <FieldLabel htmlFor="email">Email</FieldLabel>
          <FieldContent>
            <Input
              id="email"
              type="email"
              {...registerReset("email")}
              autoComplete="email"
              aria-invalid={Boolean(resetErrors.email)}
              aria-describedby={resetErrors.email ? "reset-email-error" : undefined}
            />
            <FieldDescription>
              We will send instructions to the address linked to your Cognify account.
            </FieldDescription>
            <FieldError id="reset-email-error" errors={[resetErrors.email]} />
          </FieldContent>
        </Field>

        <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
          <Button
            type="submit"
            disabled={resetPending}
          >
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
          <Alert className="border-success/30 text-success">
            <AlertDescription>Password reset instructions sent.</AlertDescription>
          </Alert>
        )}
        {resetError && (
          <Alert variant="destructive">
            <AlertDescription>{resetError}</AlertDescription>
          </Alert>
        )}
      </Form>
    );
  }

  return (
    <Form onSubmit={handleSubmit(onSubmit)} className="grid gap-5">
      <Field data-invalid={Boolean(errors.email)}>
        <FieldLabel htmlFor="login-email">Email</FieldLabel>
        <FieldContent>
          <Input
            id="login-email"
            type="email"
            {...register("email")}
            autoComplete="email"
            aria-invalid={Boolean(errors.email)}
            aria-describedby={errors.email ? "login-email-error" : "login-email-description"}
          />
          <FieldDescription id="login-email-description">
            Enter the work email tied to your procurement workspace access.
          </FieldDescription>
          <FieldError id="login-email-error" errors={[errors.email]} />
        </FieldContent>
      </Field>

      <Field data-invalid={Boolean(errors.password)}>
        <div className="flex items-center justify-between gap-3">
          <FieldLabel htmlFor="password">Password</FieldLabel>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="h-auto px-0 py-0 text-muted-foreground hover:bg-transparent hover:text-foreground"
            onClick={() => {
              setResetSent(false);
              setResetMode(true);
              setResetError(null);
            }}
          >
            Forgot password?
          </Button>
        </div>
        <FieldContent>
          <div className="relative">
            <Input
              id="password"
              type={showPassword ? "text" : "password"}
              {...register("password")}
              autoComplete="current-password"
              aria-invalid={Boolean(errors.password)}
              aria-describedby={
                errors.password ? "login-password-error" : "login-password-description"
              }
              className="pr-10"
            />
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="absolute right-0 top-0 text-muted-foreground hover:bg-transparent hover:text-foreground"
              aria-label={showPassword ? "Hide password" : "Show password"}
              onClick={() => setShowPassword((value) => !value)}
            >
              {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
            </Button>
          </div>
          <FieldDescription id="login-password-description">
            Use the password from your invited Cognify account.
          </FieldDescription>
          <FieldError id="login-password-error" errors={[errors.password]} />
        </FieldContent>
      </Field>

      <Controller
        control={control}
        name="remember"
        render={({ field }) => (
          <Field orientation="horizontal">
            <Checkbox
              id="remember"
              checked={Boolean(field.value)}
              onCheckedChange={(checked) => field.onChange(Boolean(checked))}
              onBlur={field.onBlur}
              name={field.name}
              ref={field.ref}
              aria-label="Remember me"
            />
            <FieldContent>
              <FieldLabel htmlFor="remember">Remember me</FieldLabel>
              <FieldDescription>
                Keep this browser signed in for your next procurement session.
              </FieldDescription>
            </FieldContent>
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
    </Form>
  );
}
