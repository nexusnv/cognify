"use client";

import { AlertCircle, Upload } from "lucide-react";
import { useRef, useState } from "react";
import type { ChangeEvent } from "react";
import type { ApiClientError } from "@cognify/api-client";
import type { ValidationFailedResponse } from "@cognify/api-client/schemas";
import { useAttachmentUpload } from "../hooks/use-attachments";
import { Alert, AlertDescription, AlertTitle, Button, Card, CardContent, CardDescription, CardHeader, CardTitle, Input, Progress } from "@cognify/ui";

export function AttachmentUploader({ requisitionId }: { requisitionId: string }) {
  const uploadMutation = useAttachmentUpload(requisitionId);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  function handleFileSelect(e: ChangeEvent<HTMLInputElement>) {
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
    <Card>
      <CardHeader className="gap-2">
        <CardTitle className="text-base">Upload evidence</CardTitle>
        <CardDescription>Attach procurement files and supporting records to the requisition.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
          <label className="grid gap-1.5 text-sm font-medium" htmlFor="attachment-upload">
            File
            <Input
              ref={fileInputRef}
              id="attachment-upload"
              type="file"
              onChange={handleFileSelect}
              aria-label="Upload evidence"
              disabled={uploadMutation.isPending}
            />
          </label>
          <Button
            type="button"
            onClick={handleUpload}
            disabled={!selectedFile || uploadMutation.isPending}
            className="min-h-11"
            aria-label="Upload selected file"
          >
            <Upload className="h-4 w-4" aria-hidden="true" />
            {uploadMutation.isPending ? "Uploading..." : "Upload"}
          </Button>
        </div>

        {selectedFile ? (
          <p className="text-xs text-muted-foreground">Selected file: {selectedFile.name}</p>
        ) : null}

        {uploadMutation.isPending ? <Progress value={65} /> : null}

        {uploadMutation.isError ? (
          <Alert role="alert">
            <AlertCircle className="h-4 w-4" aria-hidden="true" />
            <AlertTitle>Upload failed</AlertTitle>
            <AlertDescription>{errorMessage}</AlertDescription>
          </Alert>
        ) : null}
      </CardContent>
    </Card>
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
