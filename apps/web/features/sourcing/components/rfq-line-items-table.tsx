import { Plus, Trash2 } from "lucide-react";
import { Button } from "@cognify/ui";

export type RfqLineItemEditorValue = {
  id: string;
  name: string | null;
  description: string;
  quantity: string;
  unit: string;
  notes: string;
  estimatedUnitPrice: string | number | null;
  currency: string | null;
};

export function RfqLineItemsTable({
  items,
  errors = {},
  disabled = false,
  onChange,
}: {
  items: RfqLineItemEditorValue[];
  errors?: Record<string, string[]>;
  disabled?: boolean;
  onChange: (items: RfqLineItemEditorValue[]) => void;
}) {
  function updateItem(id: string, patch: Partial<RfqLineItemEditorValue>) {
    onChange(items.map((item) => (item.id === id ? { ...item, ...patch } : item)));
  }

  function addItem() {
    onChange([
      ...items,
      {
        id: createLocalId("line-item"),
        name: "",
        description: "",
        quantity: "1",
        unit: "each",
        notes: "",
        estimatedUnitPrice: null,
        currency: "MYR",
      },
    ]);
  }

  function removeItem(id: string) {
    onChange(items.filter((item) => item.id !== id));
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between gap-3">
        <p className="text-sm text-muted-foreground">
          Copy the requisition line context and tune the RFQ description, quantity, and unit.
        </p>
        <Button type="button" variant="outline" size="sm" onClick={addItem} disabled={disabled}>
          <Plus className="h-4 w-4" aria-hidden="true" />
          Add item
        </Button>
      </div>

      <div className="overflow-x-auto rounded-md border">
        <table className="min-w-[64rem] w-full border-separate border-spacing-0 text-sm">
          <thead className="bg-muted/40">
            <tr>
              <th className="border-b px-3 py-2 text-left font-medium">Source item</th>
              <th className="border-b px-3 py-2 text-left font-medium">Description</th>
              <th className="border-b px-3 py-2 text-left font-medium">Quantity</th>
              <th className="border-b px-3 py-2 text-left font-medium">Unit</th>
              <th className="border-b px-3 py-2 text-left font-medium">Notes</th>
              <th className="border-b px-3 py-2 text-left font-medium">Est. unit price</th>
              <th className="border-b px-3 py-2 text-left font-medium">Action</th>
            </tr>
          </thead>
          <tbody>
            {items.length === 0 ? (
              <tr>
                <td colSpan={7} className="px-3 py-5 text-sm text-muted-foreground">
                  No line items yet.
                </td>
              </tr>
            ) : null}
            {items.map((item, index) => {
              const prefix = `lineItems.${index}`;
              const descriptionError = errors[`${prefix}.description`]?.[0];
              const quantityError = errors[`${prefix}.quantity`]?.[0];
              const unitError = errors[`${prefix}.unit`]?.[0];
              const notesError = errors[`${prefix}.notes`]?.[0];

              return (
                <tr key={item.id} className="align-top">
                  <td className="border-b px-3 py-3">
                    <div className="space-y-1">
                      <p className="font-medium">{item.name || "Line item"}</p>
                      <p className="text-xs text-muted-foreground">
                        {formatEstimatedUnitPrice(item.estimatedUnitPrice, item.currency)}
                      </p>
                    </div>
                  </td>
                  <td className="border-b px-3 py-3">
                    <input
                      id={`line-items-${index}-description`}
                      className="min-h-11 w-full rounded-md border px-3 text-base"
                      value={item.description}
                      disabled={disabled}
                      aria-label={`Line item ${index + 1} description`}
                      aria-describedby={descriptionError ? `line-items-${index}-description-error` : undefined}
                      aria-invalid={Boolean(descriptionError)}
                      onChange={(event) =>
                        updateItem(item.id, { description: event.target.value })
                      }
                    />
                    {descriptionError ? (
                      <p
                        id={`line-items-${index}-description-error`}
                        className="mt-1 text-xs text-red-700"
                      >
                        {descriptionError}
                      </p>
                    ) : null}
                  </td>
                  <td className="border-b px-3 py-3">
                    <input
                      id={`line-items-${index}-quantity`}
                      type="number"
                      inputMode="decimal"
                      step="0.01"
                      className="min-h-11 w-full rounded-md border px-3 text-base"
                      value={item.quantity}
                      disabled={disabled}
                      aria-label={`Line item ${index + 1} quantity`}
                      aria-describedby={quantityError ? `line-items-${index}-quantity-error` : undefined}
                      aria-invalid={Boolean(quantityError)}
                      onChange={(event) =>
                        updateItem(item.id, { quantity: event.target.value })
                      }
                    />
                    {quantityError ? (
                      <p
                        id={`line-items-${index}-quantity-error`}
                        className="mt-1 text-xs text-red-700"
                      >
                        {quantityError}
                      </p>
                    ) : null}
                  </td>
                  <td className="border-b px-3 py-3">
                    <input
                      id={`line-items-${index}-unit`}
                      className="min-h-11 w-full rounded-md border px-3 text-base"
                      value={item.unit}
                      disabled={disabled}
                      aria-label={`Line item ${index + 1} unit`}
                      aria-describedby={unitError ? `line-items-${index}-unit-error` : undefined}
                      aria-invalid={Boolean(unitError)}
                      onChange={(event) => updateItem(item.id, { unit: event.target.value })}
                    />
                    {unitError ? (
                      <p id={`line-items-${index}-unit-error`} className="mt-1 text-xs text-red-700">
                        {unitError}
                      </p>
                    ) : null}
                  </td>
                  <td className="border-b px-3 py-3">
                    <textarea
                      id={`line-items-${index}-notes`}
                      className="min-h-20 w-full rounded-md border px-3 py-2 text-sm"
                      value={item.notes}
                      disabled={disabled}
                      aria-label={`Line item ${index + 1} notes`}
                      aria-describedby={notesError ? `line-items-${index}-notes-error` : undefined}
                      aria-invalid={Boolean(notesError)}
                      onChange={(event) => updateItem(item.id, { notes: event.target.value })}
                    />
                    {notesError ? (
                      <p id={`line-items-${index}-notes-error`} className="mt-1 text-xs text-red-700">
                        {notesError}
                      </p>
                    ) : null}
                  </td>
                  <td className="border-b px-3 py-3 font-mono tabular-nums">
                    {formatEstimatedUnitPrice(item.estimatedUnitPrice, item.currency, true)}
                  </td>
                  <td className="border-b px-3 py-3">
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => removeItem(item.id)}
                      disabled={disabled}
                    >
                      <Trash2 className="h-4 w-4" aria-hidden="true" />
                      Remove
                    </Button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function createLocalId(prefix: string) {
  const randomId = globalThis.crypto?.randomUUID?.();
  return randomId ? `${prefix}-${randomId}` : `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function formatEstimatedUnitPrice(
  value: string | number | null,
  currency: string | null,
  fallback = false,
) {
  if (value == null || value === "") {
    return fallback ? "—" : "no estimate";
  }

  const amount = typeof value === "number" ? value : Number(value);
  if (Number.isNaN(amount)) {
    return fallback ? "—" : "no estimate";
  }

  if (!currency) {
    return fallback ? String(amount) : `${amount}`;
  }

  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency,
    maximumFractionDigits: 2,
  }).format(amount);
}
