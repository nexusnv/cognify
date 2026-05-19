import { FilePlus2, Trash2 } from "lucide-react";
import { Button } from "@cognify/ui";

export type RfqRequiredDocumentEditorValue = {
  id: string;
  key: string;
  label: string;
  required: boolean;
};

export function RfqRequiredDocumentsEditor({
  items,
  errors = {},
  disabled = false,
  onChange,
}: {
  items: RfqRequiredDocumentEditorValue[];
  errors?: Record<string, string[]>;
  disabled?: boolean;
  onChange: (items: RfqRequiredDocumentEditorValue[]) => void;
}) {
  function updateItem(id: string, patch: Partial<RfqRequiredDocumentEditorValue>) {
    onChange(items.map((item) => (item.id === id ? { ...item, ...patch } : item)));
  }

  function addItem() {
    onChange([
      ...items,
      {
        id: createLocalId("required-document"),
        key: "",
        label: "",
        required: true,
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
          Capture the supporting files or declarations buyers want vendors to include.
        </p>
        <Button type="button" variant="outline" size="sm" onClick={addItem} disabled={disabled}>
          <FilePlus2 className="h-4 w-4" aria-hidden="true" />
          Add document
        </Button>
      </div>

      <div className="overflow-x-auto rounded-md border">
        <table className="min-w-[52rem] w-full border-separate border-spacing-0 text-sm">
          <thead className="bg-muted/40">
            <tr>
              <th className="border-b px-3 py-2 text-left font-medium">Key</th>
              <th className="border-b px-3 py-2 text-left font-medium">Label</th>
              <th className="border-b px-3 py-2 text-left font-medium">Required</th>
              <th className="border-b px-3 py-2 text-left font-medium">Action</th>
            </tr>
          </thead>
          <tbody>
            {items.length === 0 ? (
              <tr>
                <td colSpan={4} className="px-3 py-5 text-sm text-muted-foreground">
                  No required documents yet.
                </td>
              </tr>
            ) : null}
            {items.map((item, index) => {
              const prefix = `requiredDocuments.${index}`;
              const keyError = errors[`${prefix}.key`]?.[0];
              const labelError = errors[`${prefix}.label`]?.[0];

              return (
                <tr key={item.id} className="align-top">
                  <td className="border-b px-3 py-3">
                    <input
                      id={`required-documents-${index}-key`}
                      className="min-h-11 w-full rounded-md border px-3 text-base"
                      value={item.key}
                      disabled={disabled}
                      aria-label={`Required document ${index + 1} key`}
                      aria-describedby={keyError ? `required-documents-${index}-key-error` : undefined}
                      aria-invalid={Boolean(keyError)}
                      onChange={(event) => updateItem(item.id, { key: event.target.value })}
                    />
                    {keyError ? (
                      <p id={`required-documents-${index}-key-error`} className="mt-1 text-xs text-red-700">
                        {keyError}
                      </p>
                    ) : null}
                  </td>
                  <td className="border-b px-3 py-3">
                    <input
                      id={`required-documents-${index}-label`}
                      className="min-h-11 w-full rounded-md border px-3 text-base"
                      value={item.label}
                      disabled={disabled}
                      aria-label={`Required document ${index + 1} label`}
                      aria-describedby={labelError ? `required-documents-${index}-label-error` : undefined}
                      aria-invalid={Boolean(labelError)}
                      onChange={(event) => updateItem(item.id, { label: event.target.value })}
                    />
                    {labelError ? (
                      <p
                        id={`required-documents-${index}-label-error`}
                        className="mt-1 text-xs text-red-700"
                      >
                        {labelError}
                      </p>
                    ) : null}
                  </td>
                  <td className="border-b px-3 py-3">
                    <label
                      className="inline-flex min-h-11 items-center gap-2 text-sm"
                      aria-label={`Required document ${index + 1} required`}
                    >
                      <input
                        type="checkbox"
                        checked={item.required}
                        disabled={disabled}
                        aria-label={`Required document ${index + 1} required`}
                        onChange={(event) =>
                          updateItem(item.id, { required: event.target.checked })
                        }
                      />
                      Required
                    </label>
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
