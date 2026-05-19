import { Suspense } from "react";
import { LoginPage } from "@/features/identity/workflows/login-page";

export default function Page() {
  return (
    <Suspense>
      <LoginPage />
    </Suspense>
  );
}
