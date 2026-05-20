"use client";

import { useRef, useState } from "react";
import type { ChangeEvent, FormEvent } from "react";
import { useVendorQuotation, useVendorQuotationUpload } from "../hooks/use-vendor-quotation";
import { VendorQuotationManualEntryPanel } from "./vendor-quotation-manual-entry-panel";

const acceptedFileGuidance = "Accepted file types: PDF, DOC, DOCX, XLS, XLSX, or CSV.";
const acceptedFileTypes = [
  ".pdf",
  ".doc",
  ".docx",
  ".xls",
  ".xlsx",
  ".csv",
  "application/pdf",
  "application/msword",
  "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
  "application/vnd.ms-excel",
  "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
  "text/csv",
].join(",");

export function VendorQuotationUploadPanel({ token }: { token: string }) {
  const quotationQuery = useVendorQuotation(token);
  const uploadMutation = useVendorQuotationUpload(token);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [localError, setLocalError] = useState<string | null>(null);

  const quotation = quotationQuery.data;
  const attachments = quotation?.attachments ?? [];
  const uploadDisabled = quotation?.permissions.canUploadAttachment === false || uploadMutation.isPending;
  const statusLabel = formatQuotationStatus(quotation?.status);
  const successMessage = quotation?.status === "received" || uploadMutation.isSuccess ? "Quotation received" : null;
  const errorMessage = localError ?? getUploadErrorMessage(uploadMutation.error);

  function handleFileSelect(event: ChangeEvent<HTMLInputElement>) {
    setSelectedFile(event.target.files?.[0] ?? null);
    setLocalError(null);
    uploadMutation.reset();
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!selectedFile) {
      setLocalError("Choose a quotation file before uploading.");
      return;
    }

    try {
      await uploadMutation.mutateAsync(selectedFile);
      setSelectedFile(null);
      setLocalError(null);
      if (fileInputRef.current) {
        fileInputRef.current.value = "";
      }
    } catch {
      return;
    }
  }

  return (
    <section className="rounded-lg border p-6">
      <div className="flex flex-col gap-4">
        <div>
          <h2 className="text-lg font-semibold">Quotation upload</h2>
          <p className="mt-2 text-sm text-muted-foreground">
            Upload the buyer-requested quotation file for this RFQ invitation.
          </p>
        </div>

        <dl className="grid gap-3 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-muted-foreground">Current status</dt>
            <dd className="font-medium">{statusLabel}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Uploaded files</dt>
            <dd className="font-medium">{attachments.length}</dd>
          </div>
        </dl>

        {successMessage ? (
          <div
            role="status"
            className="rounded-md border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-950"
          >
            {successMessage}
          </div>
        ) : null}

        {quotationQuery.isLoading ? (
          <p className="text-sm text-muted-foreground">Loading quotation details.</p>
        ) : null}

        {attachments.length > 0 ? (
          <div>
            <h3 className="text-sm font-semibold">Existing uploaded files</h3>
            <ul className="mt-2 space-y-2 text-sm">
              {attachments.map((attachment) => (
                <li key={attachment.id} className="rounded-md border px-3 py-2">
                  {attachment.filename}
                </li>
              ))}
            </ul>
          </div>
        ) : (
          <p className="text-sm text-muted-foreground">No quotation files have been uploaded yet.</p>
        )}

        {quotation?.permissions.canUploadAttachment === false ? (
          <p className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
            Quotation uploads are not available for this link.
          </p>
        ) : (
          <form className="space-y-3" onSubmit={handleSubmit}>
            <div className="space-y-2">
              <label className="block text-sm font-medium" htmlFor="quotation-file">
                Quotation file
              </label>
              <input
                ref={fileInputRef}
                id="quotation-file"
                type="file"
                accept={acceptedFileTypes}
                className="block w-full text-sm text-muted-foreground file:mr-2 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-foreground hover:file:cursor-pointer"
                onChange={handleFileSelect}
                aria-label="Quotation file"
                disabled={uploadMutation.isPending}
              />
              <p className="text-xs text-muted-foreground">{acceptedFileGuidance}</p>
            </div>

            {selectedFile ? (
              <p className="text-xs text-muted-foreground">Selected file: {selectedFile.name}</p>
            ) : null}

            {errorMessage ? (
              <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
                {errorMessage}
              </div>
            ) : null}

            <button
              type="submit"
              className="inline-flex min-h-9 items-center rounded-md bg-foreground px-3 text-sm font-medium text-background disabled:opacity-50"
              disabled={uploadDisabled}
            >
              {uploadMutation.isPending ? "Uploading quotation..." : "Upload quotation"}
            </button>
          </form>
        )}

        {quotationQuery.isLoading ? null : (
          <VendorQuotationManualEntryPanel token={token} quotation={quotation ?? null} />
        )}
      </div>
    </section>
  );
}

function formatQuotationStatus(status: string | undefined | null) {
  if (!status) {
    return "No quotation uploaded yet";
  }

  return status.charAt(0).toUpperCase() + status.slice(1).replaceAll("_", " ");
}

function getUploadErrorMessage(error: unknown) {
  if (!error || typeof error !== "object") return null;

  const details =
    "data" in error
      ? (error as {
          data?: { error?: { details?: { fields?: { file?: string[] } }; message?: string } };
        }).data?.error?.details
      : null;
  const fileMessage = details?.fields?.file?.[0];
  if (fileMessage) return fileMessage;

  const message =
    "data" in error
      ? (error as { data?: { error?: { message?: string } } }).data?.error?.message
      : null;
  if (message) return message;

  return null;
}
