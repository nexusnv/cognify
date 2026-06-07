import type { SystemStatusCheck } from "@cognify/api-client/schemas";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@cognify/ui";
import { SystemStatusBadge } from "./system-status-badge";

export function SystemCheckList({ checks }: { checks: SystemStatusCheck[] }) {
  return (
    <Card aria-labelledby="system-checks-heading">
      <CardHeader>
        <CardTitle id="system-checks-heading">Checks</CardTitle>
      </CardHeader>
      <CardContent>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Check</TableHead>
              <TableHead>Message</TableHead>
              <TableHead>Status</TableHead>
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
                <TableCell>
                  <div className="space-y-1 whitespace-normal text-sm text-muted-foreground">
                    <div>{check.message}</div>
                    {check.remediation ? <div>{check.remediation}</div> : null}
                  </div>
                </TableCell>
                <TableCell>
                  <SystemStatusBadge status={check.status} />
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  );
}
