import { FilePlus2, Trash2 } from "lucide-react";
import { Button, Input, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@cognify/ui";

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
        <Table className="min-w-[52rem]">
          <TableHeader>
            <TableRow>
              <TableHead>Key</TableHead>
              <TableHead>Label</TableHead>
              <TableHead>Required</TableHead>
              <TableHead>Action</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {items.length === 0 ? (
              <TableRow>
                <TableCell colSpan={4} className="px-3 py-5 text-sm text-muted-foreground">
                  No required documents yet.
                </TableCell>
              </TableRow>
            ) : null}
            {items.map((item, index) => {
              const prefix = `requiredDocuments.${index}`;
              const keyError = errors[`${prefix}.key`]?.[0];
              const labelError = errors[`${prefix}.label`]?.[0];

              return (
                <TableRow key={item.id} className="align-top">
                  <TableCell className="px-3 py-3">
                    <Input
                      id={`required-documents-${index}-key`}
                      className="min-h-11 w-full text-base"
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
                  </TableCell>
                  <TableCell className="px-3 py-3">
                    <Input
                      id={`required-documents-${index}-label`}
                      className="min-h-11 w-full text-base"
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
                  </TableCell>
                  <TableCell className="px-3 py-3">
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
                  </TableCell>
                  <TableCell className="px-3 py-3">
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
                  </TableCell>
                </TableRow>
              );
            })}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}

function createLocalId(prefix: string) {
  const randomId = globalThis.crypto?.randomUUID?.();
  return randomId ? `${prefix}-${randomId}` : `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}
