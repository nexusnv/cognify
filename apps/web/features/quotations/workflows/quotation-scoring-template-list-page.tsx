"use client";

import Link from "next/link";
import {
  Alert,
  AlertDescription,
  Badge,
  Button,
  Card,
  CardContent,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@cognify/ui";
import { getApiErrorMessage } from "@cognify/api-client";
import {
  useDeactivateQuotationScoringTemplate,
  useQuotationScoringTemplates,
} from "../hooks/use-quotation-scoring-templates";

export function QuotationScoringTemplateListPage() {
  const templatesQuery = useQuotationScoringTemplates();
  const deactivate = useDeactivateQuotationScoringTemplate();
  const templates = templatesQuery.data ?? [];

  if (templatesQuery.isLoading) {
    return (
      <Card>
        <CardContent className="p-4 text-sm text-muted-foreground">Loading scoring templates</CardContent>
      </Card>
    );
  }

  if (templatesQuery.isError) {
    return (
      <Alert variant="destructive">
        <AlertDescription>{getApiErrorMessage(templatesQuery.error)}</AlertDescription>
      </Alert>
    );
  }

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Scoring templates</h1>
          <p className="text-sm text-muted-foreground">Maintain reusable RFQ scoring criteria for buyer evaluations.</p>
        </div>
        <Link
          className="inline-flex min-h-11 items-center rounded-md bg-foreground px-4 text-sm font-medium text-background hover:bg-foreground/90"
          href="/quotations/scoring/templates/new"
        >
          Create template
        </Link>
      </header>

      <div className="overflow-hidden rounded-md border">
        <Table className="w-full min-w-[760px] text-left text-sm">
          <TableHeader className="bg-muted/40 text-xs uppercase text-muted-foreground">
            <TableRow>
              <TableHead className="px-4 py-3">Name</TableHead>
              <TableHead className="px-4 py-3">State</TableHead>
              <TableHead className="px-4 py-3">Criteria</TableHead>
              <TableHead className="px-4 py-3">Total weight</TableHead>
              <TableHead className="px-4 py-3">Usage</TableHead>
              <TableHead className="px-4 py-3">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {templates.map((template) => (
              <TableRow key={template.id}>
                <TableCell className="px-4 py-3 font-medium">{template.name}</TableCell>
                <TableCell className="px-4 py-3">
                  <Badge variant={template.active ? "default" : "secondary"}>{template.active ? "Active" : "Inactive"}</Badge>
                </TableCell>
                <TableCell className="px-4 py-3">{template.criteria.length}</TableCell>
                <TableCell className="px-4 py-3">
                  {template.criteria.reduce((sum, criterion) => sum + Number(criterion.weight), 0).toFixed(2)}
                </TableCell>
                <TableCell className="px-4 py-3">{template.usageCount}</TableCell>
                <TableCell className="px-4 py-3">
                  <div className="flex flex-wrap gap-2">
                    {template.permissions?.canUpdate ? (
                      <Link className="text-sm font-medium underline-offset-4 hover:underline" href={`/quotations/scoring/templates/${template.id}`}>
                        Edit
                      </Link>
                    ) : null}
                    {template.active && template.permissions?.canDeactivate ? (
                      <Button
                        variant="ghost"
                        size="sm"
                        type="button"
                        onClick={() => deactivate.mutate(template.id)}
                      >
                        Deactivate
                      </Button>
                    ) : null}
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}
