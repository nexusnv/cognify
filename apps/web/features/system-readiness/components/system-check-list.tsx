import type { SystemStatusCheck } from "@cognify/api-client/schemas";
import { Alert, AlertDescription, AlertTitle, Badge, Card, CardContent, CardHeader, CardTitle, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@cognify/ui";
import { SystemStatusBadge } from "./system-status-badge";

export function SystemCheckList({ checks }: { checks: SystemStatusCheck[] }) {
  const hasDegraded = checks.some((check) => check.status !== "ok");

  return (
    <Card>
      <CardHeader className="gap-2">
        <CardTitle className="text-base">Checks</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {hasDegraded ? (
          <Alert>
            <AlertTitle>One or more checks need attention</AlertTitle>
            <AlertDescription>
              Review the remediation notes before relying on this workspace for operational work.
            </AlertDescription>
          </Alert>
        ) : null}

        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Check</TableHead>
              <TableHead>Details</TableHead>
              <TableHead className="w-28">Status</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {checks.map((check) => (
              <TableRow key={check.id}>
                <TableCell>
                  <div className="space-y-1">
                    <div className="font-medium">{check.label}</div>
                    <div className="text-xs text-muted-foreground">{check.id}</div>
                  </div>
                </TableCell>
                <TableCell className="space-y-1 text-sm text-muted-foreground">
                  <div>{check.message}</div>
                  {check.remediation ? <div>{check.remediation}</div> : null}
                </TableCell>
                <TableCell>
                  <div className="space-y-2">
                    <SystemStatusBadge status={check.status} />
                    <Badge variant="outline">{check.status}</Badge>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  );
}
