"use client";

import { useState } from "react";
import { Button, Card, CardContent, CardDescription, CardHeader, CardTitle, Tabs, TabsList, TabsTrigger } from "@cognify/ui";
import type { ListSupplierInvoiceQueueMatchingStatus, ListSupplierInvoiceQueueStatus, SupplierInvoiceQueueItem } from "@cognify/api-client/schemas";
import { InvoiceQueueSummary } from "../components/invoice-queue-summary";
import { InvoiceReviewPanel } from "../components/invoice-review-panel";
import { useAccountsPayableInvoices } from "../hooks/use-accounts-payable-invoices";
import { AccountsPayableInvoiceQueueTable } from "../tables/accounts-payable-invoice-queue-table";

const statusTabs: Array<{ label: string; value: string; status?: ListSupplierInvoiceQueueStatus }> = [
  { label: "Needs review", value: "captured", status: "captured" },
  { label: "In review", value: "in_review", status: "in_review" },
  { label: "Needs information", value: "needs_information", status: "needs_information" },
  { label: "Reviewed", value: "reviewed", status: "reviewed" },
  { label: "All", value: "all" },
];

const matchingFilters: Array<{ label: string; value: ListSupplierInvoiceQueueMatchingStatus | undefined }> = [
  { label: "All", value: undefined },
  { label: "Pending", value: "pending" },
  { label: "Matched", value: "matched" },
  { label: "Mismatch", value: "mismatch" },
];

export function AccountsPayableInvoiceQueuePage() {
  const [status, setStatus] = useState("captured");
  const [matchingFilter, setMatchingFilter] = useState<ListSupplierInvoiceQueueMatchingStatus | undefined>();
  const [selectedInvoice, setSelectedInvoice] = useState<SupplierInvoiceQueueItem | null>(null);
  const activeTab = statusTabs.find((tab) => tab.value === status) ?? statusTabs[0];
  const invoicesQuery = useAccountsPayableInvoices(
    activeTab.status
      ? { status: activeTab.status, ...(matchingFilter ? { matchingStatus: matchingFilter } : {}) }
      : { ...(matchingFilter ? { matchingStatus: matchingFilter } : {}) },
  );
  const invoices = invoicesQuery.data ?? [];

  return (
    <section className="space-y-6">
      <header className="space-y-2">
        <h1 className="text-2xl font-semibold tracking-normal">Invoice review</h1>
        <p className="text-sm text-muted-foreground">
          Review captured supplier invoices before matching and approval.
        </p>
      </header>

      <InvoiceQueueSummary invoices={invoices} />

      <Card>
        <CardHeader>
          <CardTitle>Review queue</CardTitle>
          <CardDescription>Captured supplier invoices across purchase orders for the active tenant.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <Tabs
            value={status}
            onValueChange={(nextStatus) => {
              setStatus(nextStatus);
              setSelectedInvoice(null);
            }}
          >
            <TabsList aria-label="Invoice review states" className="flex h-auto w-full flex-wrap justify-start">
              {statusTabs.map((tab) => (
                <TabsTrigger key={tab.value} value={tab.value} className="min-h-11 px-3">
                  {tab.label}
                </TabsTrigger>
              ))}
            </TabsList>
          </Tabs>

          <div className="flex gap-2">
            {matchingFilters.map((filter) => (
              <Button
                key={filter.label}
                variant={matchingFilter === filter.value ? "default" : "outline"}
                size="sm"
                onClick={() => {
                  setMatchingFilter(filter.value);
                  setSelectedInvoice(null);
                }}
              >
                {filter.label}
              </Button>
            ))}
          </div>

          <div className="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_28rem]">
            <AccountsPayableInvoiceQueueTable
              invoices={invoices}
              state={
                invoicesQuery.isLoading
                  ? "loading"
                  : invoicesQuery.isError
                    ? "error"
                    : invoices.length === 0
                      ? "empty"
                      : "idle"
              }
              onSelect={setSelectedInvoice}
              errorTitle={queueErrorTitle(invoicesQuery.error)}
            />
            <InvoiceReviewPanel
              invoice={selectedInvoice}
              onMutationSettled={() => {
                void invoicesQuery.refetch();
              }}
            />
          </div>
        </CardContent>
      </Card>
    </section>
  );
}

function queueErrorTitle(error: unknown) {
  if (typeof error === "object" && error !== null && "error" in error) {
    const apiError = (error as { error?: { code?: string; message?: string } }).error;
    if (apiError?.code === "forbidden") {
      return "You are not allowed to perform this action.";
    }

    if (apiError?.message) {
      return apiError.message;
    }
  }

  return "Invoice review queue unavailable";
}
