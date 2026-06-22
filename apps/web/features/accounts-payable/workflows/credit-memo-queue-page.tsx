"use client";

import { useState } from "react";
import { Button, Card, CardContent, CardHeader, Skeleton } from "@cognify/ui";
import type { SupplierCreditMemoStatus } from "@cognify/api-client/schemas";
import { useSupplierCreditMemos } from "../hooks/use-supplier-credit-memos";
import { CreditMemoStatusBadge } from "../components/credit-memo-status-badge";

type TabKey = "all" | SupplierCreditMemoStatus;

const tabs: Array<{ key: TabKey; label: string }> = [
  { key: "all", label: "All" },
  { key: "draft", label: "Draft" },
  { key: "pending_approval", label: "Pending approval" },
  { key: "open", label: "Open" },
  { key: "partially_applied", label: "Partially applied" },
  { key: "fully_applied", label: "Fully applied" },
  { key: "closed", label: "Closed" },
  { key: "voided", label: "Voided" },
];

export function CreditMemoQueuePage() {
  const [tab, setTab] = useState<TabKey>("all");
  const filters = tab === "all" ? {} : { status: tab };
  const { data, isLoading, isError, error } = useSupplierCreditMemos(filters);

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-32 w-full" />
      </div>
    );
  }

  if (isError) {
    return <div className="text-destructive">{(error as Error)?.message ?? "Failed to load credit memo queue."}</div>;
  }

  const memos = data?.data ?? [];

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">Credit memos</h1>

      <div className="flex gap-2 border-b overflow-x-auto">
        {tabs.map((t) => (
          <Button key={t.key} variant={tab === t.key ? "default" : "ghost"} onClick={() => setTab(t.key)} className="rounded-b-none">
            {t.label}
          </Button>
        ))}
      </div>

      <div className="space-y-2">
        {memos.length === 0 ? (
          <Card>
            <CardContent className="py-6 text-center text-muted-foreground">No credit memos in this status.</CardContent>
          </Card>
        ) : (
          memos.map((memo) => (
            <Card key={memo.id}>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <span className="text-base font-semibold">{memo.number}</span>
                  <CreditMemoStatusBadge status={memo.status} />
                </div>
              </CardHeader>
              <CardContent className="text-sm text-muted-foreground">
                <p>Vendor: {memo.vendorName ?? memo.vendorId}</p>
                <p>Original invoice: {memo.originalInvoiceNumber ?? memo.originalInvoiceId ?? "—"}</p>
                <p>
                  {memo.appliedAmount ?? "0"} / {memo.totalAmount} {memo.currency} applied
                </p>
              </CardContent>
            </Card>
          ))
        )}
      </div>
    </div>
  );
}
