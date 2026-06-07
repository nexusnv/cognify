import type { SystemStatusDemo } from "@cognify/api-client/schemas";
import { Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";

const countLabels: Array<{ key: keyof SystemStatusDemo["counts"]; label: string }> = [
  { key: "tenants", label: "Tenants" },
  { key: "users", label: "Users" },
  { key: "requisitions", label: "Requisitions" },
  { key: "vendors", label: "Vendors" },
  { key: "rfqs", label: "RFQs" },
  { key: "quotations", label: "Quotations" },
  { key: "approvalTasks", label: "Approval tasks" },
  { key: "awards", label: "Awards" },
];

export function DemoDatasetSummary({ demo }: { demo: SystemStatusDemo }) {
  return (
    <Card aria-labelledby="demo-dataset-heading">
      <CardHeader>
        <CardTitle id="demo-dataset-heading">Demo dataset</CardTitle>
        <p className="text-sm text-muted-foreground">
          {demo.seeded ? "Seeded locally" : "Not seeded yet"}
          {demo.lastSeededAt ? (
            <>
              {" "}
              · Last seeded <time dateTime={demo.lastSeededAt}>{demo.lastSeededAt}</time>
            </>
          ) : null}
        </p>
      </CardHeader>
      <CardContent>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {countLabels.map(({ key, label }) => (
            <Card key={key} size="sm">
              <CardContent>
                <div className="text-xs text-muted-foreground">{label}</div>
                <div className="mt-1 text-2xl font-semibold">{demo.counts[key]}</div>
              </CardContent>
            </Card>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}
