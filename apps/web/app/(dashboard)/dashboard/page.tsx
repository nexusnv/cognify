import Link from "next/link";
import { FileText, Plus } from "lucide-react";

export default function DashboardPage() {
  return (
    <section className="space-y-5">
      <div className="flex flex-col gap-3 border-b pb-4 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Dashboard</h1>
          <p className="mt-1 text-sm text-muted-foreground">Procurement work queue and requisition starting point.</p>
        </div>
        <div className="flex flex-col gap-2 sm:flex-row">
          <Link
            href="/requisitions/new"
            className="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-foreground px-4 text-sm font-medium text-background"
          >
            <Plus className="h-4 w-4" aria-hidden="true" />
            New requisition
          </Link>
          <Link
            href="/requisitions"
            className="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border px-4 text-sm font-medium"
          >
            <FileText className="h-4 w-4" aria-hidden="true" />
            View requisitions
          </Link>
        </div>
      </div>

      <div className="grid gap-3 md:grid-cols-3">
        {[
          ["Drafts", "1", "Resume requester work"],
          ["Submitted", "1", "Ready for review"],
          ["Needs attention", "0", "No blocked requisitions"],
        ].map(([label, value, helper]) => (
          <section key={label} className="rounded-md border p-4">
            <p className="text-sm font-medium text-muted-foreground">{label}</p>
            <p className="mt-2 font-mono text-2xl font-semibold tabular-nums">{value}</p>
            <p className="mt-1 text-sm text-muted-foreground">{helper}</p>
          </section>
        ))}
      </div>

      <section className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Recent requisition activity</h2>
        <div className="mt-3 space-y-3 text-sm">
          <div className="flex items-start justify-between gap-3 rounded-md border p-3">
            <div>
              <p className="font-medium">REQ-2026-000002 submitted</p>
              <p className="text-muted-foreground">Warehouse packing supplies moved to review.</p>
            </div>
            <Link href="/requisitions/req-2" className="min-h-11 rounded-md border px-3 py-2">
              Open
            </Link>
          </div>
        </div>
      </section>
    </section>
  );
}
