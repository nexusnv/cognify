import type { SystemStatusDemo } from "@cognify/api-client/schemas";

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
    <section aria-labelledby="demo-dataset-heading" className="space-y-3">
      <div className="space-y-1">
        <h2 id="demo-dataset-heading" className="text-base font-semibold">
          Demo dataset
        </h2>
        <p className="text-sm text-muted-foreground">
          {demo.seeded ? "Seeded locally" : "Not seeded yet"}
          {demo.lastSeededAt ? (
            <>
              {" "}
              · Last seeded <time dateTime={demo.lastSeededAt}>{demo.lastSeededAt}</time>
            </>
          ) : null}
        </p>
      </div>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {countLabels.map(({ key, label }) => (
          <div key={key} className="rounded-md border p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-2xl font-semibold">{demo.counts[key]}</div>
          </div>
        ))}
      </div>
    </section>
  );
}
