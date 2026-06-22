"use client";

import { useRouter } from "next/navigation";
import { CreditMemoCreatePanel } from "../components/credit-memo-create-panel";

export function CreditMemoCreatePage() {
  const router = useRouter();
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">New credit memo</h1>
      <CreditMemoCreatePanel
        onSuccess={(id) => router.push(`/accounts-payable/credit-memos/${id}`)}
        onCancel={() => router.push("/accounts-payable/credit-memos")}
      />
    </div>
  );
}
