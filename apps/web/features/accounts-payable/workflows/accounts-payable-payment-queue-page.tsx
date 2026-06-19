"use client";

import { useState } from "react";
import { Button, Card, CardContent, CardDescription, CardHeader, CardTitle, Tabs, TabsList, TabsTrigger } from "@cognify/ui";
import type { SupplierInvoiceQueueItem } from "@cognify/api-client/schemas";
import { useAccountsPayableInvoices } from "../hooks/use-accounts-payable-invoices";
import { useApPaymentHandoffs } from "../hooks/use-payment-handoffs";
import { useRetryInvoicePaymentInduction } from "../hooks/use-payment-holds";
import { AccountsPayableInvoiceQueueTable } from "../tables/accounts-payable-invoice-queue-table";
import { PaymentHoldPanel } from "../components/payment-hold-panel";
import { PaymentHandoffCreateDialog } from "../components/payment-handoff-create-dialog";
import { ActiveHandoffsSection } from "../components/active-handoffs-section";

const paymentFilterTabs: Array<{ label: string; value: string }> = [
  { label: "All", value: "any" },
  { label: "Payment eligible", value: "payment_eligible" },
  { label: "On hold", value: "on_hold" },
  { label: "Payment ready", value: "payment_ready" },
  { label: "Exported", value: "handoff_exported" },
  { label: "Awaiting induction", value: "none" },
];

export function AccountsPayablePaymentQueuePage() {
  const [paymentStatus, setPaymentStatus] = useState("any");
  const [selectedInvoice, setSelectedInvoice] = useState<SupplierInvoiceQueueItem | null>(null);
  const [handoffDialogOpen, setHandoffDialogOpen] = useState(false);

  const invoicesQuery = useAccountsPayableInvoices({
    paymentStatus,
  });
  const invoices = invoicesQuery.data ?? [];
  const handoffsQuery = useApPaymentHandoffs();

  const eligibleInvoices = invoices.filter(
    (inv) => inv.paymentStatus === "payment_eligible" || !inv.paymentStatus,
  );

  return (
    <section className="space-y-6">
      <header className="flex items-start justify-between gap-4">
        <div className="space-y-2">
          <h1 className="text-2xl font-semibold tracking-normal">Payment queue</h1>
          <p className="text-sm text-muted-foreground">
            Manage payment-ready invoices and AP handoffs.
          </p>
        </div>
        <Button onClick={() => setHandoffDialogOpen(true)}>
          Create handoff
        </Button>
      </header>

      <Card>
        <CardHeader>
          <CardTitle>Payment queue</CardTitle>
          <CardDescription>
            Supplier invoices with payment status across the active tenant.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <Tabs
            value={paymentStatus}
            onValueChange={(value) => {
              setPaymentStatus(value);
              setSelectedInvoice(null);
            }}
          >
            <TabsList aria-label="Payment states" className="flex h-auto w-full flex-wrap justify-start">
              {paymentFilterTabs.map((tab) => (
                <TabsTrigger key={tab.value} value={tab.value} className="min-h-11 px-3">
                  {tab.label}
                </TabsTrigger>
              ))}
            </TabsList>
          </Tabs>

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
            <div className="space-y-4">
              {selectedInvoice?.paymentStatus && (
                <PaymentHoldPanel
                  invoiceId={selectedInvoice.id}
                  paymentStatus={selectedInvoice.paymentStatus}
                  paymentOnHoldReason={selectedInvoice.paymentOnHoldReason}
                  paymentOnHoldAt={selectedInvoice.paymentOnHoldAt}
                  paymentOnHoldByUserId={selectedInvoice.paymentOnHoldByUserId}
                  lockVersion={selectedInvoice.lockVersion}
                  onMutationSettled={() => {
                    void invoicesQuery.refetch();
                    void handoffsQuery.refetch();
                  }}
                />
              )}
              {selectedInvoice && !selectedInvoice.paymentStatus && (
                <RetryInductionPanel
                  invoiceId={selectedInvoice.id}
                  lockVersion={selectedInvoice.lockVersion}
                  onMutationSettled={() => {
                    void invoicesQuery.refetch();
                    void handoffsQuery.refetch();
                  }}
                />
              )}
            </div>
          </div>
        </CardContent>
      </Card>

      <ActiveHandoffsSection />

      <PaymentHandoffCreateDialog
        open={handoffDialogOpen}
        onOpenChange={(open) => {
          setHandoffDialogOpen(open);
          if (!open) {
            void invoicesQuery.refetch();
            void handoffsQuery.refetch();
          }
        }}
        eligibleInvoices={eligibleInvoices}
      />
    </section>
  );
}

function RetryInductionPanel({
  invoiceId,
  lockVersion,
  onMutationSettled,
}: {
  invoiceId: string;
  lockVersion: number;
  onMutationSettled: () => void;
}) {
  const [error, setError] = useState<string | null>(null);
  const retryMutation = useRetryInvoicePaymentInduction(invoiceId);

  function handleRetry() {
    setError(null);
    retryMutation.mutate(
      { lockVersion },
      {
        onSettled: () => {
          onMutationSettled();
        },
        onError: (err) => {
          const message =
            typeof err === "object" && err !== null && "message" in err
              ? (err as { message: string }).message
              : "Failed to retry payment induction.";
          setError(message);
        },
      },
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Payment Induction</CardTitle>
        <CardDescription>This invoice has not been inducted into the payment pipeline.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {error && <p className="text-sm text-destructive">{error}</p>}
        <Button
          variant="outline"
          className="w-full"
          onClick={handleRetry}
          disabled={retryMutation.isPending}
        >
          {retryMutation.isPending ? "Retrying..." : "Retry induction"}
        </Button>
      </CardContent>
    </Card>
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

  return "Payment queue unavailable";
}
