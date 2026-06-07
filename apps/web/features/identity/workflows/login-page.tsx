"use client";

import {
  Badge,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Separator,
} from "@cognify/ui";
import { CheckCircle2, ClipboardCheck, FileSearch, ShieldCheck } from "lucide-react";
import { useRouter, useSearchParams } from "next/navigation";
import { LoginForm } from "../forms/login-form";

const proofPoints = [
  {
    title: "Governed intake",
    body: "Start requisitions with budget, policy, and approval context attached.",
    icon: ClipboardCheck,
  },
  {
    title: "Audit-ready workflow",
    body: "Keep comments, attachments, decisions, and handoffs in one traceable path.",
    icon: ShieldCheck,
  },
  {
    title: "Supplier decisions",
    body: "Compare quotations and preserve evidence before award recommendations.",
    icon: FileSearch,
  },
];

export function LoginPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const next = safeNextPath(searchParams.get("next"));

  return (
    <div className="min-h-svh bg-muted/40 px-4 py-6 text-foreground md:px-8 md:py-10">
      <div className="mx-auto flex min-h-[calc(100svh-3rem)] w-full max-w-6xl items-center justify-center">
        <div className="grid w-full gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(360px,430px)] lg:items-stretch">
          <section className="flex min-h-[520px] flex-col justify-between rounded-xl border bg-card p-6 shadow-sm md:p-8">
            <div className="space-y-8">
              <div className="flex items-center justify-between gap-4">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">
                    Enterprise procurement
                  </p>
                  <p className="text-2xl font-semibold text-foreground">Cognify</p>
                </div>
                <Badge variant="secondary">Secure workspace</Badge>
              </div>

              <div className="max-w-2xl space-y-4">
                <Badge variant="outline" className="w-fit">
                  First-party procurement control
                </Badge>
                <div className="space-y-3">
                  <h2 className="text-3xl font-semibold tracking-normal md:text-5xl">
                    Control spend before it becomes a surprise.
                  </h2>
                  <p className="max-w-xl text-base leading-7 text-muted-foreground">
                    Route every request through budget context, sourcing evidence,
                    approval policy, and audit history before teams commit spend.
                  </p>
                </div>
              </div>
            </div>

            <div className="grid gap-3 md:grid-cols-3">
              {proofPoints.map((item) => {
                const Icon = item.icon;

                return (
                  <article key={item.title} className="rounded-lg border bg-background/60 p-4">
                    <Icon className="size-5 text-primary" aria-hidden="true" />
                    <h3 className="mt-3 text-sm font-medium">{item.title}</h3>
                    <p className="mt-2 text-sm leading-6 text-muted-foreground">{item.body}</p>
                  </article>
                );
              })}
            </div>
          </section>

          <Card className="self-center p-0">
            <CardHeader className="gap-2 px-6 pt-6 text-center md:px-8 md:pt-8">
              <div className="mx-auto flex size-10 items-center justify-center rounded-md bg-primary text-primary-foreground">
                <CheckCircle2 className="size-5" aria-hidden="true" />
              </div>
              <h1 className="text-2xl font-semibold">Sign in to your procurement workspace</h1>
              <CardTitle className="sr-only">Sign in to your procurement workspace</CardTitle>
              <CardDescription>
                Use your Cognify account to continue{next !== "/dashboard" ? " where you left off" : ""}.
              </CardDescription>
            </CardHeader>
            <CardContent className="px-6 pb-6 md:px-8 md:pb-8">
              <LoginForm onAuthenticated={() => router.replace(next)} />
              <Separator className="my-6" />
              <p className="text-center text-xs leading-5 text-muted-foreground">
                Access is limited to invited workspace members. Contact your
                procurement administrator if you need an account.
              </p>
            </CardContent>
          </Card>
        </div>
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
