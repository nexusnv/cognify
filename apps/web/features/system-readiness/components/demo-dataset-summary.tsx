import type { SystemStatusDemo } from "@cognify/api-client/schemas";
import {
  Alert,
  AlertDescription,
  AlertTitle,
  Badge,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Progress,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@cognify/ui";

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
    <Card>
      <CardHeader className="gap-2">
        <CardTitle className="text-base">Demo dataset</CardTitle>
        <CardDescription>
          {demo.seeded ? "Seeded locally" : "Not seeded yet"}
          {demo.lastSeededAt ? (
            <>
              {" "}
              · Last seeded <time dateTime={demo.lastSeededAt}>{demo.lastSeededAt}</time>
            </>
          ) : null}
        </CardDescription>
        <Progress value={demo.seeded ? 100 : 0} />
      </CardHeader>
      <CardContent className="space-y-4">
        {!demo.seeded ? (
          <Alert>
            <AlertTitle>Demo data is missing</AlertTitle>
            <AlertDescription>
              Seed the tenant before using the preview environment for procurement flows.
            </AlertDescription>
          </Alert>
        ) : null}

        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Dataset</TableHead>
              <TableHead>Count</TableHead>
              <TableHead>Key</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {countLabels.map(({ key, label }) => (
              <TableRow key={key}>
                <TableCell>{label}</TableCell>
                <TableCell className="font-semibold">{demo.counts[key]}</TableCell>
                <TableCell>
                  <Badge variant="outline">{key}</Badge>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  );
}
