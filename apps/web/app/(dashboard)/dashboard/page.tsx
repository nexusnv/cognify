import Link from "next/link";
import { FileText, Plus, RefreshCw } from "lucide-react";
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Separator,
} from "@cognify/ui";

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
          <Button asChild className="min-h-11">
            <Link href="/requisitions/new">
              <Plus className="h-4 w-4" aria-hidden="true" />
              New requisition
            </Link>
          </Button>
          <Button asChild variant="outline" className="min-h-11">
            <Link href="/requisitions">
              <FileText className="h-4 w-4" aria-hidden="true" />
              View requisitions
            </Link>
          </Button>
        </div>
      </div>

      <div className="grid gap-3 md:grid-cols-3">
        {[
          { label: "Drafts", value: "1", helper: "Resume requester work", badge: "Active" },
          { label: "Submitted", value: "1", helper: "Ready for review", badge: "In queue" },
          { label: "Needs attention", value: "0", helper: "No blocked requisitions", badge: "Clear" },
        ].map((item) => (
          <Card key={item.label}>
            <CardHeader className="gap-3">
              <div className="flex items-center justify-between gap-3">
                <CardTitle className="text-sm font-medium text-muted-foreground">{item.label}</CardTitle>
                <Badge variant="secondary">{item.badge}</Badge>
              </div>
              <CardDescription className="text-3xl font-semibold text-foreground">{item.value}</CardDescription>
            </CardHeader>
            <CardContent className="pt-0">
              <p className="text-sm text-muted-foreground">{item.helper}</p>
            </CardContent>
          </Card>
        ))}
      </div>

      <Card>
        <CardHeader className="gap-3">
          <div className="flex items-center justify-between gap-3">
            <div>
              <CardTitle className="text-base">Recent requisition activity</CardTitle>
              <CardDescription>Latest queue events and next actions.</CardDescription>
            </div>
            <Button variant="ghost" size="sm">
              <RefreshCw className="h-4 w-4" aria-hidden="true" />
              Refresh
            </Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-3">
          <Separator />
          <div className="flex items-start justify-between gap-3 rounded-md bg-muted/30 p-3 text-sm">
            <div className="space-y-1">
              <p className="font-medium">REQ-2026-000002 submitted</p>
              <p className="text-muted-foreground">Warehouse packing supplies moved to review.</p>
            </div>
            <Button asChild variant="outline" size="sm">
              <Link href="/requisitions/req-2">Open</Link>
            </Button>
          </div>
        </CardContent>
      </Card>
    </section>
  );
}
