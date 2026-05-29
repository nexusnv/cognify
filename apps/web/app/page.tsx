import { Badge, Card, CardContent, CardHeader, CardTitle, buttonVariants } from "@cognify/ui";
import { ArrowRight, ClipboardCheck, FileSearch, ShieldCheck } from "lucide-react";
import Link from "next/link";

const workspaceHref = "/login?next=%2Fdashboard";

const capabilities = [
  {
    title: "Governed requests",
    body: "Capture budget, policy, and requester context before procurement work starts.",
    icon: ClipboardCheck,
  },
  {
    title: "Comparable evidence",
    body: "Normalize quotation input and keep sourcing decisions attached to the record.",
    icon: FileSearch,
  },
  {
    title: "Audit-ready approvals",
    body: "Preserve delegation, approvals, handoffs, and attachments in the workflow trail.",
    icon: ShieldCheck,
  },
];

export default function Home() {
  return (
    <main id="main-content" className="min-h-svh bg-muted/40 text-foreground">
      <div className="mx-auto flex min-h-svh w-full max-w-6xl flex-col px-4 py-6 md:px-8 md:py-8">
        <header className="flex items-center justify-between gap-4 border-b pb-5">
          <div>
            <p className="text-sm font-medium text-muted-foreground">Procurement governance</p>
            <h1 className="text-2xl font-semibold">Cognify</h1>
          </div>
          <Link
            href={workspaceHref}
            className={buttonVariants({ variant: "outline", className: "min-h-10" })}
          >
            Sign in
          </Link>
        </header>

        <section className="grid flex-1 gap-8 py-8 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-center lg:py-12">
          <div className="space-y-8">
            <div className="max-w-3xl space-y-5">
              <Badge variant="secondary">Enterprise procurement workspace</Badge>
              <div className="space-y-4">
                <h2 className="text-4xl font-semibold md:text-6xl">
                  One controlled path from request to award.
                </h2>
                <p className="max-w-2xl text-base leading-7 text-muted-foreground md:text-lg">
                  Cognify gives procurement teams a signed-in workspace for
                  requisitions, approvals, sourcing evidence, and audit-ready
                  handoffs.
                </p>
              </div>
              <div className="flex flex-col gap-3 sm:flex-row">
                <Link
                  href={workspaceHref}
                  className={buttonVariants({ className: "min-h-11 px-5" })}
                >
                  Open workspace
                  <ArrowRight className="size-4" aria-hidden="true" />
                </Link>
                <Link
                  href="/login"
                  className={buttonVariants({
                    variant: "ghost",
                    className: "min-h-11 px-5",
                  })}
                >
                  Sign in
                </Link>
              </div>
            </div>

            <div className="grid gap-4 md:grid-cols-3">
              {capabilities.map((item) => {
                const Icon = item.icon;

                return (
                  <Card key={item.title} className="gap-3 py-0">
                    <CardHeader className="px-5 pt-5">
                      <div className="flex size-9 items-center justify-center rounded-md bg-primary/10 text-primary">
                        <Icon className="size-4" aria-hidden="true" />
                      </div>
                      <CardTitle className="text-base">{item.title}</CardTitle>
                    </CardHeader>
                    <CardContent className="px-5 pb-5">
                      <p className="text-sm leading-6 text-muted-foreground">{item.body}</p>
                    </CardContent>
                  </Card>
                );
              })}
            </div>
          </div>

          <Card className="overflow-hidden py-0">
            <CardHeader className="border-b bg-card px-5 py-5">
              <Badge variant="outline" className="w-fit">
                Workspace entry
              </Badge>
              <CardTitle className="text-xl">Sign in before dashboard access</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4 px-5 py-5">
              <div className="rounded-lg border bg-background p-4">
                <p className="text-sm font-medium">Acme Procurement</p>
                <p className="mt-1 text-sm text-muted-foreground">
                  Dashboard, requisitions, sourcing, and approvals are available
                  after authentication.
                </p>
              </div>
              <Link href={workspaceHref} className={buttonVariants({ className: "w-full" })}>
                Open workspace
              </Link>
            </CardContent>
          </Card>
        </section>
      </div>
    </main>
  );
}
