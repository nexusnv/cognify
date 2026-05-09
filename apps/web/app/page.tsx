import Link from "next/link";

export default function Home() {
  return (
    <main id="main-content" className="min-h-screen bg-background text-foreground">
      <div className="mx-auto flex min-h-screen w-full max-w-6xl flex-col gap-8 px-6 py-10">
        <header className="flex items-center justify-between border-b pb-6">
          <div>
            <p className="text-sm text-muted-foreground">Procurement governance</p>
            <h1 className="text-3xl font-semibold">Cognify</h1>
          </div>
          <Link
            href="/dashboard"
            className="rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background"
          >
            Open workspace
          </Link>
        </header>

        <section className="grid gap-4 md:grid-cols-3">
          {[
            ["Requisitions", "Lifecycle, approvals, budgets, and audit trail."],
            ["Quotations", "Ingestion, OCR, normalization, and comparison."],
            ["Governance", "Risk scoring, vendor checks, and evidence packs."],
          ].map(([title, body]) => (
            <article key={title} className="rounded-lg border bg-card p-5">
              <h2 className="text-lg font-medium">{title}</h2>
              <p className="mt-2 text-sm text-muted-foreground">{body}</p>
            </article>
          ))}
        </section>
      </div>
    </main>
  );
}
