import type {
  VendorPortalRfqLineItem,
  VendorPortalRfqRequiredDocument,
} from "@cognify/api-client/schemas";
import {
  Card,
  CardContent,
  CardHeader,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@cognify/ui";
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
      <Card className="py-0">
        <CardHeader className="border-b bg-muted/30">
          <p className="text-sm font-medium text-muted-foreground">{invitation.tenant.name ?? "Cognify"}</p>
          <h1 className="text-3xl font-medium leading-tight text-card-foreground">{invitation.rfq.title}</h1>
          <p className="font-mono text-sm text-muted-foreground">{invitation.rfq.number}</p>
        </CardHeader>
        <CardContent className="py-4">
          <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950">
            {invitation.deadlineSummary}
          </div>
        </CardContent>
      </Card>

      <Card className="py-0">
        <CardHeader className="border-b bg-muted/30">
          <h2 className="text-lg font-medium text-card-foreground">Invitation</h2>
        </CardHeader>
        <CardContent className="py-4">
          <dl className="grid gap-3 text-sm sm:grid-cols-2">
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
        </CardContent>
      </Card>

      <Card className="py-0">
        <CardHeader className="border-b bg-muted/30">
          <h2 className="text-lg font-medium text-card-foreground">Scope</h2>
        </CardHeader>
        <CardContent className="space-y-4 py-4">
          <p className="text-sm text-muted-foreground">
            {invitation.rfq.scopeSummary ?? "No scope summary was provided."}
          </p>
          <div className="space-y-2">
            <h3 className="text-base font-semibold">Response instructions</h3>
            <p className="text-sm text-muted-foreground">
              {invitation.rfq.responseInstructions ?? "No response instructions were provided."}
            </p>
          </div>
        </CardContent>
      </Card>

      <Card className="py-0">
        <CardHeader className="border-b bg-muted/30">
          <h2 className="text-lg font-medium text-card-foreground">Line items</h2>
        </CardHeader>
        <CardContent className="py-4">
          {lineItems.length > 0 ? (
            <Table className="text-sm">
              <TableHeader className="bg-muted/40">
                <TableRow>
                  <TableHead className="border-b px-3 py-2">Description</TableHead>
                  <TableHead className="border-b px-3 py-2">Quantity</TableHead>
                  <TableHead className="border-b px-3 py-2">Unit</TableHead>
                  <TableHead className="border-b px-3 py-2">Notes</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {lineItems.map((item, index) => (
                  <TableRow key={`${item.description ?? "item"}-${index}`}>
                    <TableCell className="border-b px-3 py-3 font-medium">
                      {item.description ?? "Untitled item"}
                    </TableCell>
                    <TableCell className="border-b px-3 py-3">{item.quantity ?? "-"}</TableCell>
                    <TableCell className="border-b px-3 py-3">{item.unit ?? "-"}</TableCell>
                    <TableCell className="border-b px-3 py-3">{item.notes ?? "-"}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          ) : (
            <p className="text-sm text-muted-foreground">No line items were provided.</p>
          )}
        </CardContent>
      </Card>

      <Card className="py-0">
        <CardHeader className="border-b bg-muted/30">
          <h2 className="text-lg font-medium text-card-foreground">Required documents</h2>
        </CardHeader>
        <CardContent className="py-4">
          {requiredDocuments.length > 0 ? (
            <ul className="space-y-2 text-sm">
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
            <p className="text-sm text-muted-foreground">No required documents were listed.</p>
          )}
        </CardContent>
      </Card>

      <VendorQuotationUploadPanel token={token} />
    </article>
  );
}
