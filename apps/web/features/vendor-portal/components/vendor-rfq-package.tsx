import type {
  VendorPortalRfqLineItem,
  VendorPortalRfqRequiredDocument,
} from "@cognify/api-client/schemas";
import { Badge, Card, CardContent, CardHeader, CardTitle, Separator, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@cognify/ui";
import type { VendorRfqPortalViewModel } from "../types/vendor-rfq-portal-view-model";
import { formatDateTime } from "../types/vendor-rfq-portal-view-model";
import { VendorQuotationUploadPanel } from "./vendor-quotation-upload-panel";

export function VendorRfqPackage({
  invitation,
  token,
}: {
  invitation: VendorRfqPortalViewModel;
  token: string;
}) {
  const requiredDocuments: VendorPortalRfqRequiredDocument[] = invitation.rfq.requiredDocuments;
  const lineItems: VendorPortalRfqLineItem[] = invitation.rfq.lineItems;

  return (
    <article className="mx-auto max-w-5xl space-y-6 px-4 py-8">
      <Card>
        <CardHeader>
        <p className="text-sm font-medium text-muted-foreground">{invitation.tenant.name ?? "Cognify"}</p>
        <h1 className="mt-2 text-3xl font-semibold">{invitation.rfq.title}</h1>
        <p className="mt-2 font-mono text-sm text-muted-foreground">{invitation.rfq.number}</p>
        </CardHeader>
        <CardContent>
        <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950">
          {invitation.deadlineSummary}
        </div>
        </CardContent>
      </Card>

      <Card><CardHeader><CardTitle className="text-lg">Invitation</CardTitle></CardHeader><CardContent>
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
      </CardContent></Card>

      <Card><CardHeader><CardTitle className="text-lg">Scope</CardTitle></CardHeader><CardContent>
        <p className="mt-3 text-sm text-muted-foreground">
          {invitation.rfq.scopeSummary ?? "No scope summary was provided."}
        </p>
        <Separator className="my-4" />
        <h3 className="mt-6 text-base font-semibold">Response instructions</h3>
        <p className="mt-2 text-sm text-muted-foreground">
          {invitation.rfq.responseInstructions ?? "No response instructions were provided."}
        </p>
      </CardContent></Card>

      <Card><CardHeader><CardTitle className="text-lg">Line items</CardTitle></CardHeader><CardContent>
        {lineItems.length > 0 ? (
          <div className="mt-4 overflow-x-auto">
            <Table>
              <TableHeader><TableRow><TableHead>Description</TableHead><TableHead>Quantity</TableHead><TableHead>Unit</TableHead><TableHead>Notes</TableHead></TableRow></TableHeader>
              <TableBody>
                {lineItems.map((item, index) => (
                  <TableRow key={`${item.description ?? "item"}-${index}`}>
                    <TableCell className="font-medium">{item.description ?? "Untitled item"}</TableCell>
                    <TableCell>{item.quantity ?? "-"}</TableCell>
                    <TableCell>{item.unit ?? "-"}</TableCell>
                    <TableCell>{item.notes ?? "-"}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        ) : (
          <p className="mt-3 text-sm text-muted-foreground">No line items were provided.</p>
        )}
      </CardContent></Card>

      <Card><CardHeader><CardTitle className="text-lg">Required documents</CardTitle></CardHeader><CardContent>
        {requiredDocuments.length > 0 ? (
          <ul className="mt-4 space-y-2 text-sm">
            {requiredDocuments.map((document) => (
              <li
                key={document.key ?? document.label}
                className="flex items-center justify-between rounded-md border p-3"
              >
                <span>{document.label ?? document.key ?? "Required document"}</span>
                <Badge variant={document.required ? "secondary" : "outline"}>{document.required ? "Required" : "Optional"}</Badge>
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-3 text-sm text-muted-foreground">No required documents were listed.</p>
        )}
      </CardContent></Card>

      <VendorQuotationUploadPanel token={token} />
    </article>
  );
}
