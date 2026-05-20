"use client";

import { useRef, useState, type ChangeEvent } from "react";
import { getApiErrorMessage } from "@cognify/api-client";
import { Button } from "@cognify/ui";
import {
  useQuotationAttachments,
  useRfqInvitationQuotation,
  useRfqInvitationQuotationUpload,
} from "../hooks/use-quotation-upload";

const uploadableInvitationStatuses = new Set(["sent", "acknowledged"]);

export function QuotationEvidencePanel({
  invitationId,
  invitationStatus,
}: {
  invitationId: string;
  invitationStatus: string;
}) {
  const quotationQuery = useRfqInvitationQuotation(invitationId);
  const quotation = quotationQuery.data ?? null;
  const attachmentsQuery = useQuotationAttachments(quotation?.id ?? null);
  const uploadMutation = useRfqInvitationQuotationUpload(invitationId);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  const canUpload = uploadableInvitationStatuses.has(invitationStatus);
  const attachments = attachmentsQuery.data ?? quotation?.attachments ?? [];
  const hasQuotation = quotation !== null;
  const fileCount = quotation?.fileCount ?? attachments.length;
  const errorMessage = quotationQuery.isError
    ? getApiErrorMessage(quotationQuery.error)
    : uploadMutation.isError
      ? getApiErrorMessage(uploadMutation.error)
      : null;

  function handleFileSelect(event: ChangeEvent<HTMLInputElement>) {
    setSelectedFile(event.target.files?.[0] ?? null);
    uploadMutation.reset();
  }

  async function handleUpload() {
    if (!selectedFile || !canUpload || uploadMutation.isPending) return;

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
    <section className="rounded-md border border-dashed p-3">
      <div className="space-y-3">
        <div className="space-y-1">
          <h4 className="text-sm font-semibold">{hasQuotation ? "Quotation received" : "Quotation evidence"}</h4>
          {hasQuotation ? (
            <p className="text-sm text-muted-foreground">
              {fileCount} file{fileCount === 1 ? "" : "s"} received
            </p>
          ) : (
            <p className="text-sm text-muted-foreground">No quotation files received yet.</p>
          )}
        </div>

        {attachments.length > 0 ? (
          <ul className="space-y-1 text-sm">
            {attachments.map((attachment) => (
              <li key={attachment.id} className="truncate text-foreground">
                {attachment.filename}
              </li>
            ))}
          </ul>
        ) : null}

        <div className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
          <label className="block text-sm font-medium">
            Buyer-received quotation file
            <input
              ref={fileInputRef}
              type="file"
              className="mt-1 block w-full text-sm text-muted-foreground file:mr-2 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-foreground hover:file:cursor-pointer disabled:opacity-50"
              onChange={handleFileSelect}
              disabled={!canUpload || uploadMutation.isPending}
            />
          </label>

          <Button type="button" onClick={() => void handleUpload()} disabled={!selectedFile || !canUpload || uploadMutation.isPending}>
            Upload buyer-received quotation
          </Button>
        </div>

        {selectedFile ? <p className="text-xs text-muted-foreground">Selected file: {selectedFile.name}</p> : null}

        {errorMessage ? (
          <p role="alert" className="text-sm text-red-700">
            {errorMessage}
          </p>
        ) : null}
      </div>
    </section>
  );
}
