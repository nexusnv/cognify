import { Input, RadioGroup, RadioGroupItem } from "@cognify/ui";
import type { SupplierInvoiceReviewChecklist } from "@cognify/api-client/schemas";

export type ChecklistKey = keyof SupplierInvoiceReviewChecklist;
export type ChecklistStatus = SupplierInvoiceReviewChecklist[ChecklistKey]["status"];

export const checklistItems: Array<{ key: ChecklistKey; label: string }> = [
  { key: "completeness", label: "Completeness" },
  { key: "coding", label: "Coding" },
  { key: "attachment", label: "Attachment" },
  { key: "vendorIdentity", label: "Vendor identity" },
  { key: "poLinkage", label: "PO linkage" },
];

export function buildEmptyChecklist(): SupplierInvoiceReviewChecklist {
  return {
    completeness: { status: "needs_attention", note: null },
    coding: { status: "needs_attention", note: null },
    attachment: { status: "needs_attention", note: null },
    vendorIdentity: { status: "needs_attention", note: null },
    poLinkage: { status: "needs_attention", note: null },
  };
}

export function InvoiceReviewChecklist({
  value,
  onChange,
}: {
  value: SupplierInvoiceReviewChecklist;
  onChange: (value: SupplierInvoiceReviewChecklist) => void;
}) {
  return (
    <div className="space-y-3">
      {checklistItems.map((item) => (
        <div key={item.key} className="rounded-md border p-3">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <p className="font-medium">{item.label}</p>
            <RadioGroup
              value={value[item.key].status}
              onValueChange={(status) =>
                onChange({
                  ...value,
                  [item.key]: {
                    ...value[item.key],
                    status: status as ChecklistStatus,
                  },
                })
              }
              className="flex flex-wrap gap-3"
            >
              <label className="flex items-center gap-2 text-sm">
                <RadioGroupItem value="pass" />
                <span>{item.label} passed</span>
              </label>
              <label className="flex items-center gap-2 text-sm">
                <RadioGroupItem value="fail" />
                <span>{item.label} failed</span>
              </label>
              <label className="flex items-center gap-2 text-sm">
                <RadioGroupItem value="needs_attention" />
                <span>{item.label} needs attention</span>
              </label>
            </RadioGroup>
          </div>
          <Input
            className="mt-3"
            aria-label={`${item.label} note`}
            placeholder="Reviewer note"
            value={value[item.key].note ?? ""}
            onChange={(event) =>
              onChange({
                ...value,
                [item.key]: {
                  ...value[item.key],
                  note: event.target.value || null,
                },
              })
            }
          />
        </div>
      ))}
    </div>
  );
}
