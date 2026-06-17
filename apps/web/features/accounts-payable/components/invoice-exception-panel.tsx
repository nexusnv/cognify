"use client";

import { Alert, AlertDescription, AlertTitle, Button, Card, CardContent, CardHeader, CardTitle, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@cognify/ui";
import { AlertCircle } from "lucide-react";
import { useState } from "react";
import type { SupplierInvoiceException } from "@cognify/api-client/schemas";
import { useInvoiceExceptions, useResolveException, useEscalateException } from "../hooks/use-invoice-exceptions";
import { InvoiceExceptionStatusBadge } from "./invoice-exception-status-badge";
import { InvoiceExceptionResolutionForm } from "./invoice-exception-resolution-form";
import { InvoiceExceptionEscalateForm } from "./invoice-exception-escalate-form";

interface InvoiceExceptionPanelProps {
  supplierInvoiceId: string;
}

export function InvoiceExceptionPanel({ supplierInvoiceId }: InvoiceExceptionPanelProps) {
  const { data: exceptions, isLoading, error } = useInvoiceExceptions(supplierInvoiceId);
  const resolveMutation = useResolveException(supplierInvoiceId);
  const escalateMutation = useEscalateException(supplierInvoiceId);
  const [selectedException, setSelectedException] = useState<SupplierInvoiceException | null>(null);
  const [escalatingException, setEscalatingException] = useState<SupplierInvoiceException | null>(null);

  if (isLoading) return <div className="text-sm text-muted-foreground">Loading exceptions...</div>;
  if (error) {
    return (
      <Alert variant="destructive">
        <AlertCircle className="h-4 w-4" />
        <AlertTitle>Error</AlertTitle>
        <AlertDescription>Failed to load exceptions.</AlertDescription>
      </Alert>
    );
  }
  if (!exceptions?.length) {
    return <div className="text-sm text-muted-foreground">No exceptions found.</div>;
  }

  return (
    <>
      <Card>
        <CardHeader>
          <CardTitle className="text-lg">Invoice exceptions ({exceptions.length})</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Dimension</TableHead>
                <TableHead>Expected</TableHead>
                <TableHead>Actual</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {exceptions.map((exc) => (
                <TableRow key={exc.id}>
                  <TableCell className="font-medium">{exc.dimension}</TableCell>
                  <TableCell>{exc.expectedValue ?? "\u2014"}</TableCell>
                  <TableCell>{exc.actualValue ?? "\u2014"}</TableCell>
                  <TableCell>
                    <InvoiceExceptionStatusBadge status={exc.status} />
                  </TableCell>
                  <TableCell className="text-right">
                    {exc.status === "open" && (
                      <div className="flex justify-end gap-2">
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => setEscalatingException(exc)}
                        >
                          Escalate
                        </Button>
                        <Button
                          size="sm"
                          onClick={() => setSelectedException(exc)}
                        >
                          Resolve
                        </Button>
                      </div>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      {resolveMutation.error && (
        <Alert variant="destructive" role="alert">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{errorToMessage(resolveMutation.error)}</AlertDescription>
        </Alert>
      )}

      {escalateMutation.error && (
        <Alert variant="destructive" role="alert">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{errorToMessage(escalateMutation.error)}</AlertDescription>
        </Alert>
      )}

      {selectedException && (
        <InvoiceExceptionResolutionForm
          exception={selectedException}
          open={!!selectedException}
          onOpenChange={() => setSelectedException(null)}
          onSubmit={(data) => {
            resolveMutation.mutate(
              { exceptionId: selectedException.id, payload: data },
              { onSuccess: () => setSelectedException(null) },
            );
          }}
          isPending={resolveMutation.isPending}
        />
      )}

      {escalatingException && (
        <InvoiceExceptionEscalateForm
          exception={escalatingException}
          open={!!escalatingException}
          onOpenChange={() => setEscalatingException(null)}
          onSubmit={(data) => {
            escalateMutation.mutate(
              { exceptionId: escalatingException.id, payload: data },
              { onSuccess: () => setEscalatingException(null) },
            );
          }}
          isPending={escalateMutation.isPending}
        />
      )}
    </>
  );
}

function errorToMessage(error: unknown) {
  if (typeof error === "object" && error !== null && "error" in error) {
    const apiError = (error as { error?: { message?: string } }).error;
    if (apiError?.message) return apiError.message;
  }
  if (error instanceof Error) return error.message;
  return "An unexpected error occurred.";
}
