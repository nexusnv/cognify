"use client";

import { useState } from "react";
import { Button, Card, CardContent, CardHeader, CardTitle, Input, Label } from "@cognify/ui";
import type { SupplierCreditMemoLine } from "@cognify/api-client/schemas";
import {
  useAddSupplierCreditMemoLine,
  useUpdateSupplierCreditMemoLine,
  useRemoveSupplierCreditMemoLine,
} from "../hooks/use-supplier-credit-memo-lines";

interface CreditMemoLineEditorProps {
  creditMemoId: string;
  lockVersion: number;
  lines: SupplierCreditMemoLine[];
}

export function CreditMemoLineEditor({ creditMemoId, lockVersion, lines }: CreditMemoLineEditorProps) {
  const addLine = useAddSupplierCreditMemoLine(creditMemoId);
  const updateLine = useUpdateSupplierCreditMemoLine(creditMemoId);
  const removeLine = useRemoveSupplierCreditMemoLine(creditMemoId);

  const [description, setDescription] = useState("");
  const [quantity, setQuantity] = useState("1");
  const [unitPrice, setUnitPrice] = useState("0");
  const [taxCode, setTaxCode] = useState("");
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editDescription, setEditDescription] = useState("");
  const [editQuantity, setEditQuantity] = useState("1");
  const [editUnitPrice, setEditUnitPrice] = useState("0");

  function handleAdd(e: React.FormEvent) {
    e.preventDefault();
    addLine.mutate(
      {
        lockVersion,
        lineNumber: lines.length + 1,
        description,
        quantity,
        unitPrice,
        taxCode: taxCode || undefined,
      },
      {
        onSuccess: () => {
          setDescription("");
          setQuantity("1");
          setUnitPrice("0");
          setTaxCode("");
        },
      },
    );
  }

  function handleUpdate(lineId: string) {
    updateLine.mutate(
      {
        lineId,
        payload: {
          lockVersion,
          description: editDescription,
          quantity: editQuantity,
          unitPrice: editUnitPrice,
        },
      },
      {
        onSuccess: () => setEditingId(null),
      },
    );
  }

  function handleRemove(lineId: string) {
    removeLine.mutate({ lineId, lockVersion });
  }

  function startEdit(line: SupplierCreditMemoLine) {
    setEditingId(line.id);
    setEditDescription(line.description);
    setEditQuantity(line.quantity);
    setEditUnitPrice(line.unitPrice);
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Lines</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {lines.length > 0 && (
          <div className="space-y-2">
            {lines.map((line) => (
              <div key={line.id} className="flex items-center gap-2 rounded border p-2 text-sm">
                {editingId === line.id ? (
                  <>
                    <Input
                      value={editDescription}
                      onChange={(e) => setEditDescription(e.target.value)}
                      className="flex-1"
                      placeholder="Description"
                    />
                    <Input
                      value={editQuantity}
                      onChange={(e) => setEditQuantity(e.target.value)}
                      className="w-20"
                      placeholder="Qty"
                    />
                    <Input
                      value={editUnitPrice}
                      onChange={(e) => setEditUnitPrice(e.target.value)}
                      className="w-28"
                      placeholder="Unit price"
                    />
                    <Button type="button" size="sm" onClick={() => handleUpdate(line.id)}>
                      Save
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => setEditingId(null)}>
                      Cancel
                    </Button>
                  </>
                ) : (
                  <>
                    <span className="flex-1">{line.description}</span>
                    <span className="text-muted-foreground">Qty: {line.quantity}</span>
                    <span className="text-muted-foreground">@ {line.unitPrice}</span>
                    <span className="font-medium">{line.lineSubtotal}</span>
                    <Button type="button" size="sm" variant="outline" onClick={() => startEdit(line)}>
                      Edit
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => handleRemove(line.id)}>
                      Remove
                    </Button>
                  </>
                )}
              </div>
            ))}
          </div>
        )}

        <form onSubmit={handleAdd} className="flex items-end gap-2">
          <div className="flex-1 space-y-1">
            <Label htmlFor="line-description">Description</Label>
            <Input
              id="line-description"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              required
            />
          </div>
          <div className="w-20 space-y-1">
            <Label htmlFor="line-quantity">Qty</Label>
            <Input
              id="line-quantity"
              value={quantity}
              onChange={(e) => setQuantity(e.target.value)}
              required
            />
          </div>
          <div className="w-28 space-y-1">
            <Label htmlFor="line-unit-price">Unit price</Label>
            <Input
              id="line-unit-price"
              value={unitPrice}
              onChange={(e) => setUnitPrice(e.target.value)}
              required
            />
          </div>
          <div className="w-24 space-y-1">
            <Label htmlFor="line-tax-code">Tax code</Label>
            <Input
              id="line-tax-code"
              value={taxCode}
              onChange={(e) => setTaxCode(e.target.value)}
            />
          </div>
          <Button type="submit" size="sm" disabled={addLine.isPending}>
            {addLine.isPending ? "Adding…" : "Add line"}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
