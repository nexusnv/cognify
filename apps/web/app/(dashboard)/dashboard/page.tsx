import Link from "next/link";
import { FileText, Plus } from "lucide-react";
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@cognify/ui";

const metrics = [
  {
    label: "Drafts",
    value: "1",
    helper: "Resume requester work",
  },
  {
    label: "Submitted",
    value: "1",
    helper: "Ready for review",
  },
  {
    label: "Needs attention",
    value: "0",
    helper: "No blocked requisitions",
  },
];

const activity = [
  {
    requisition: "REQ-2026-000002",
    summary: "Warehouse packing supplies moved to review.",
    status: "Submitted",
    nextStep: "Procurement review",
    href: "/requisitions/req-2",
  },
];

export default function DashboardPage() {
  return (
    <section className="space-y-5">
      <div className="flex flex-col gap-3 border-b pb-4 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Dashboard</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Procurement work queue and requisition starting point.
          </p>
        </div>
        <div className="flex flex-col gap-2 sm:flex-row">
          <Button asChild size="lg">
            <Link href="/requisitions/new">
              <Plus className="h-4 w-4" aria-hidden="true" />
              New requisition
            </Link>
          </Button>
          <Button asChild variant="outline" size="lg">
            <Link href="/requisitions">
              <FileText className="h-4 w-4" aria-hidden="true" />
              View requisitions
            </Link>
          </Button>
        </div>
      </div>

      <div className="grid gap-3 md:grid-cols-3">
        {metrics.map((metric) => (
          <Card key={metric.label} className="py-0">
            <CardHeader className="border-b bg-muted/30">
              <CardTitle>
                <h2>{metric.label}</h2>
              </CardTitle>
              <CardDescription>{metric.helper}</CardDescription>
            </CardHeader>
            <CardContent className="py-4">
              <p className="font-mono text-2xl font-semibold tabular-nums">{metric.value}</p>
            </CardContent>
          </Card>
        ))}
      </div>

      <Card className="py-0">
        <CardHeader className="border-b bg-muted/30">
          <CardTitle>Recent requisition activity</CardTitle>
          <CardDescription>Latest records that still need procurement attention.</CardDescription>
        </CardHeader>
        <CardContent className="py-4">
          <Table aria-label="Recent requisition activity">
            <TableHeader>
              <TableRow>
                <TableHead>Requisition</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Next step</TableHead>
                <TableHead className="text-right">Action</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {activity.map((item) => (
                <TableRow key={item.requisition}>
                  <TableCell>
                    <div className="space-y-1">
                      <p className="font-medium">{item.requisition}</p>
                      <p className="text-muted-foreground">{item.summary}</p>
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge variant="secondary">{item.status}</Badge>
                  </TableCell>
                  <TableCell>{item.nextStep}</TableCell>
                  <TableCell className="text-right">
                    <Button asChild variant="outline">
                      <Link href={item.href} aria-label={`Open requisition ${item.requisition}`}>
                        Open
                      </Link>
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </section>
  );
}
