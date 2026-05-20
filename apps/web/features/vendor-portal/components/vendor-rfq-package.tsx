import type {
  VendorPortalRfqLineItem,
  VendorPortalRfqRequiredDocument,
} from "@cognify/api-client/schemas";
import type { VendorRfqPortalViewModel } from "../types/vendor-rfq-portal-view-model";
import { formatDateTime } from "../types/vendor-rfq-portal-view-model";

export function VendorRfqPackage({ invitation }: { invitation: VendorRfqPortalViewModel }) {
  const requiredDocuments: VendorPortalRfqRequiredDocument[] = invitation.rfq.requiredDocuments;
  const lineItems: VendorPortalRfqLineItem[] = invitation.rfq.lineItems;

  return (
    <article className="mx-auto max-w-5xl space-y-6 px-4 py-8">
      <header className="rounded-lg border bg-background p-6 shadow-sm">
        <p className="text-sm font-medium text-muted-foreground">{invitation.tenant.name ?? "Cognify"}</p>
        <h1 className="mt-2 text-3xl font-semibold">{invitation.rfq.title}</h1>
        <p className="mt-2 font-mono text-sm text-muted-foreground">{invitation.rfq.number}</p>
        <div className="mt-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950">
          {invitation.deadlineSummary}
        </div>
      </header>

      <section className="rounded-lg border p-6">
        <h2 className="text-lg font-semibold">Invitation</h2>
        <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-muted-foreground">Vendor</dt>
            <dd className="font-medium">{invitation.vendor.name}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Contact</dt>
            <dd>{invitation.vendor.contactName ?? "Not recorded"}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Status</dt>
            <dd className="capitalize">{invitation.invitation.status}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Response due</dt>
            <dd>{invitation.rfq.responseDueAt ? formatDateTime(invitation.rfq.responseDueAt) : "Not set"}</dd>
          </div>
        </dl>
      </section>

      <section className="rounded-lg border p-6">
        <h2 className="text-lg font-semibold">Scope</h2>
        <p className="mt-3 text-sm text-muted-foreground">
          {invitation.rfq.scopeSummary ?? "No scope summary was provided."}
        </p>
        <h3 className="mt-6 text-base font-semibold">Response instructions</h3>
        <p className="mt-2 text-sm text-muted-foreground">
          {invitation.rfq.responseInstructions ?? "No response instructions were provided."}
        </p>
      </section>

      <section className="rounded-lg border p-6">
        <h2 className="text-lg font-semibold">Line items</h2>
        {lineItems.length > 0 ? (
          <div className="mt-4 overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b text-muted-foreground">
                <tr>
                  <th className="py-2 pr-3 font-medium">Description</th>
                  <th className="py-2 pr-3 font-medium">Quantity</th>
                  <th className="py-2 pr-3 font-medium">Unit</th>
                  <th className="py-2 font-medium">Notes</th>
                </tr>
              </thead>
              <tbody>
                {lineItems.map((item, index) => (
                  <tr key={`${item.description ?? "item"}-${index}`} className="border-b last:border-b-0">
                    <td className="py-3 pr-3 font-medium">{item.description ?? "Untitled item"}</td>
                    <td className="py-3 pr-3">{item.quantity ?? "-"}</td>
                    <td className="py-3 pr-3">{item.unit ?? "-"}</td>
                    <td className="py-3">{item.notes ?? "-"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <p className="mt-3 text-sm text-muted-foreground">No line items were provided.</p>
        )}
      </section>

      <section className="rounded-lg border p-6">
        <h2 className="text-lg font-semibold">Required documents</h2>
        {requiredDocuments.length > 0 ? (
          <ul className="mt-4 space-y-2 text-sm">
            {requiredDocuments.map((document) => (
              <li
                key={document.key ?? document.label}
                className="flex items-center justify-between rounded-md border p-3"
              >
                <span>{document.label ?? document.key ?? "Required document"}</span>
                <span className="text-muted-foreground">{document.required ? "Required" : "Optional"}</span>
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-3 text-sm text-muted-foreground">No required documents were listed.</p>
        )}
      </section>

      <section className="rounded-lg border border-blue-300 bg-blue-50 p-4 text-sm text-blue-950">
        Quotation submission will be available in a later Cognify workflow. Use the buyer
        instructions above to prepare your response.
      </section>
    </article>
  );
}
