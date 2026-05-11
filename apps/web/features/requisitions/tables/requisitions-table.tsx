import Link from "next/link";
import { ExternalLink } from "lucide-react";
import { RequisitionStatusBadge } from "../components/requisition-status-badge";
import type { Requisition } from "../types/requisition-view-model";
import { formatMoney } from "../utils/requisition-totals";

export function RequisitionsTable({ requisitions }: { requisitions: Requisition[] }) {
  return (
    <>
      <div className="hidden overflow-hidden rounded-md border md:block">
        <table className="w-full table-fixed text-left text-sm">
          <thead className="border-b bg-card text-xs uppercase text-muted-foreground">
            <tr>
              <th className="w-36 px-3 py-3">Number</th>
              <th className="px-3 py-3">Title</th>
              <th className="w-36 px-3 py-3">Status</th>
              <th className="w-36 px-3 py-3">Requester</th>
              <th className="w-32 px-3 py-3">Needed by</th>
              <th className="w-36 px-3 py-3 text-right">Estimated total</th>
              <th className="w-24 px-3 py-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            {requisitions.map((requisition) => (
              <tr key={requisition.id} className="border-b last:border-b-0">
                <td className="px-3 py-4 font-mono text-xs tabular-nums">{requisition.number}</td>
                <td className="px-3 py-4 font-medium">{requisition.title}</td>
                <td className="px-3 py-4">
                  <RequisitionStatusBadge status={requisition.status} />
                </td>
                <td className="px-3 py-4 text-muted-foreground">{requisition.requester.name}</td>
                <td className="px-3 py-4 tabular-nums">{requisition.neededByDate}</td>
                <td className="px-3 py-4 text-right font-mono tabular-nums">
                  {formatMoney(requisition.estimatedTotal, requisition.currency)}
                </td>
                <td className="px-3 py-4">
                  <Link
                    href={`/requisitions/${requisition.id}`}
                    className="inline-flex min-h-11 items-center gap-2 rounded-md border px-3"
                  >
                    Open
                    <ExternalLink className="h-4 w-4" aria-hidden="true" />
                  </Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="space-y-3 md:hidden">
        {requisitions.map((requisition) => (
          <Link key={requisition.id} href={`/requisitions/${requisition.id}`} className="block rounded-md border p-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="font-medium">{requisition.title}</p>
                <p className="mt-1 font-mono text-xs text-muted-foreground">{requisition.number}</p>
              </div>
              <RequisitionStatusBadge status={requisition.status} />
            </div>
            <div className="mt-3 flex items-center justify-between text-sm">
              <span>Needed {requisition.neededByDate}</span>
              <span className="font-mono tabular-nums">{formatMoney(requisition.estimatedTotal, requisition.currency)}</span>
            </div>
          </Link>
        ))}
      </div>
    </>
  );
}
