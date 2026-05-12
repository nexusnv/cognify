"use client";

import { LoginForm } from "../forms/login-form";

export function LoginPage() {
  return (
    <div className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6">
      <h1 className="text-2xl font-semibold">Sign in to Cognify</h1>
      <div className="mt-6">
        <LoginForm />
      </div>
    </div>
  );
}