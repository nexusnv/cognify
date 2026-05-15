import Link from "next/link";
import type { ProjectRequisition } from "../types/project-view-model";

const groups = [
  { id: "draft", label: "Draft" },
  { id: "submitted", label: "Submitted" },
  { id: "changes_requested", label: "Changes requested" },
  { id: "stopped", label: "Stopped" },
] as const;

export function ProjectRequisitionPipeline({ requisitions }: { requisitions: ProjectRequisition[] }) {
  return (
    <section id="pipeline" className="rounded-md border p-4">
      <h2 className="text-base font-semibold">Requisition pipeline</h2>
      <div className="mt-3 space-y-4">
        {groups.map((group) => {
          const rows = requisitions.filter((item) => groupForStatus(item.status) === group.id);
          return (
            <div key={group.id} className="space-y-2">
              <h3 className="text-sm font-medium text-muted-foreground">{group.label}</h3>
              {rows.length === 0 ? (
                <p className="text-sm text-muted-foreground">No requisitions in this stage.</p>
              ) : (
                rows.map((row) => (
                  <Link
                    key={row.id}
                    href={`/requisitions/${row.id}`}
                    className="grid gap-2 rounded-md border p-3 text-sm sm:grid-cols-[8.5rem_minmax(0,1fr)_10rem_8rem_7rem]"
                  >
                    <span className="font-mono text-xs tabular-nums">{row.number}</span>
                    <span className="font-medium">{row.title}</span>
                    <span className="text-muted-foreground">{row.requester?.name ?? "Unknown"}</span>
                    <span className="font-mono tabular-nums">{formatMoney(row.estimatedTotal, "MYR")}</span>
                    <span className="text-muted-foreground">{formatStatus(row.status)}</span>
                  </Link>
                ))
              )}
            </div>
          );
        })}
      </div>
    </section>
  );
}

function groupForStatus(status: string) {
  if (status === "draft") return "draft";
  if (status === "submitted") return "submitted";
  if (status === "changes_requested") return "changes_requested";
  return "stopped";
}

function formatStatus(status: string) {
  if (status === "changes_requested") return "Changes requested";
  if (status === "on_hold") return "On hold";
  return status.charAt(0).toUpperCase() + status.slice(1);
}

function formatMoney(amount: number, currency: string) {
  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency,
    maximumFractionDigits: 2,
  }).format(amount);
}
