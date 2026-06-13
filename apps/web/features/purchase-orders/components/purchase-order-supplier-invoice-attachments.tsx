"use client";

import { AlertCircle, Upload } from "lucide-react";
import { useRef, useState } from "react";
import { Button, Input } from "@cognify/ui";
import { useSupplierInvoiceAttachments, useUploadSupplierInvoiceAttachment } from "../hooks/use-purchase-order-supplier-invoices";
import { errorToMessage } from "../utils/error-helpers";

function formatFileSize(bytes: number) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function PurchaseOrderSupplierInvoiceAttachments({
  supplierInvoiceId,
  purchaseOrderId,
}: {
  supplierInvoiceId: string;
  purchaseOrderId: string;
}) {
  const attachmentsQuery = useSupplierInvoiceAttachments(supplierInvoiceId);
  const uploadMutation = useUploadSupplierInvoiceAttachment(supplierInvoiceId, purchaseOrderId);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  async function handleUpload() {
    if (!selectedFile) return;

    try {
      await uploadMutation.mutateAsync(selectedFile);
      setSelectedFile(null);
      if (fileInputRef.current) {
        fileInputRef.current.value = "";
      }
    } catch {
      return;
    }
  }

  return (
    <div className="mt-3 rounded-md border bg-muted/10 p-3">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p className="text-sm font-medium">Attachments</p>
          <p className="text-xs text-muted-foreground">
            {attachmentsQuery.isLoading
              ? "Loading attachments..."
              : `${attachmentsQuery.data?.length ?? 0} attachment(s)`}
          </p>
        </div>
      </div>

      <div className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
        <Input
          ref={fileInputRef}
          type="file"
          aria-label="Upload invoice attachment"
          onChange={(event) => setSelectedFile(event.target.files?.[0] ?? null)}
          disabled={uploadMutation.isPending}
          className="block w-full text-sm text-muted-foreground file:mr-2 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-foreground hover:file:cursor-pointer"
        />
        {selectedFile ? (
          <Button type="button" onClick={() => void handleUpload()} disabled={uploadMutation.isPending}>
            <Upload className="h-4 w-4" aria-hidden="true" />
            {uploadMutation.isPending ? "Uploading..." : "Upload attachment"}
          </Button>
        ) : null}
      </div>

      {attachmentsQuery.isError ? (
        <div role="alert" className="mt-3 rounded-md border border-red-300 bg-red-50 p-2 text-sm text-red-900">
          Could not load invoice attachments.
        </div>
      ) : null}

      {uploadMutation.isError ? (
        <div role="alert" className="mt-3 flex items-center gap-2 rounded-md border border-red-300 bg-red-50 p-2 text-sm text-red-900">
          <AlertCircle className="h-4 w-4" aria-hidden="true" />
          <span>{errorToMessage(uploadMutation.error) ?? "Upload failed. Please try again."}</span>
        </div>
      ) : null}

      {!attachmentsQuery.isLoading && !attachmentsQuery.isError && (attachmentsQuery.data?.length ?? 0) === 0 ? (
        <p className="mt-3 text-sm text-muted-foreground">
          No invoice attachments uploaded yet.
        </p>
      ) : null}

      {(attachmentsQuery.data?.length ?? 0) > 0 ? (
        <div className="mt-3 space-y-2">
          {attachmentsQuery.data?.map((attachment) => (
            <div key={attachment.id} className="rounded-md border bg-background p-2 text-sm">
              <p className="font-medium">{attachment.filename}</p>
              <p className="text-xs text-muted-foreground">
                {formatFileSize(attachment.sizeBytes)}
                {attachment.uploadedBy ? ` · by ${attachment.uploadedBy.name}` : ""}
              </p>
            </div>
          ))}
        </div>
      ) : null}
    </div>
  );
}
