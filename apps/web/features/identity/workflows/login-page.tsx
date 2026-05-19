"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { LoginForm } from "../forms/login-form";

export function LoginPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const next = safeNextPath(searchParams.get("next"));

  return (
    <div className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6">
      <h1 className="text-2xl font-semibold">Sign in to Cognify</h1>
      <div className="mt-6">
        <LoginForm onAuthenticated={() => router.replace(next)} />
      </div>
    </div>
  );
}

function safeNextPath(value: string | null): string {
  if (!value || !value.startsWith("/") || value.startsWith("//")) {
    return "/dashboard";
  }

  return value;
}
