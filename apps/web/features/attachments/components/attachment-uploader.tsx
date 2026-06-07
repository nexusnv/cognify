"use client";

import { AlertCircle, Upload } from "lucide-react";
import { useRef, useState } from "react";
import type { ApiClientError } from "@cognify/api-client";
import type { ValidationFailedResponse } from "@cognify/api-client/schemas";
import { Button, Input } from "@cognify/ui";
import { useAttachmentUpload } from "../hooks/use-attachments";

export function AttachmentUploader({ requisitionId }: { requisitionId: string }) {
  const uploadMutation = useAttachmentUpload(requisitionId);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  function handleFileSelect(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0] ?? null;
    setSelectedFile(file);
    uploadMutation.reset();
  }

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

  const errorMessage = getUploadErrorMessage(uploadMutation.error);

  return (
    <div className="space-y-2">
      <label className="block text-sm font-medium" htmlFor="attachment-upload">
        Upload evidence
      </label>
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
        <Input
          ref={fileInputRef}
          id="attachment-upload"
          type="file"
          className="block w-full text-sm text-muted-foreground file:mr-2 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-foreground hover:file:cursor-pointer"
          onChange={handleFileSelect}
          aria-label="Upload evidence"
          disabled={uploadMutation.isPending}
        />
        {selectedFile ? (
          <Button
            type="button"
            onClick={handleUpload}
            disabled={uploadMutation.isPending}
            className="min-h-9"
            aria-label="Upload selected file"
          >
            <Upload className="h-4 w-4" aria-hidden="true" />
            {uploadMutation.isPending ? "Uploading..." : "Upload"}
          </Button>
        ) : null}
      </div>
      {selectedFile ? (
        <p className="text-xs text-muted-foreground">Selected file: {selectedFile.name}</p>
      ) : null}
      {uploadMutation.isError ? (
        <div
          role="alert"
          className="flex items-center gap-2 rounded-md border border-red-300 bg-red-50 p-2 text-sm text-red-900"
        >
          <AlertCircle className="h-4 w-4" aria-hidden="true" />
          <span>{errorMessage}</span>
        </div>
      ) : null}
    </div>
  );
}

function getUploadErrorMessage(error: ApiClientError<ValidationFailedResponse> | null) {
  const serverError = error?.data;
  const fieldMessage = serverError?.error?.details?.fields?.file?.[0];
  if (fieldMessage) return fieldMessage;

  const errorMessage = serverError?.error?.message;
  if (errorMessage) return errorMessage;

  return "Upload failed. Please try again.";
}
