"use client";

import { AlertCircle, Download, Eye, Loader2, Trash2 } from "lucide-react";
import { useState } from "react";
import { useRightPanel } from "@/components/right-panel/right-panel-provider";
import { AttachmentPreviewPanel } from "./attachment-preview-panel";
import { downloadAttachmentBlob } from "../api/attachments-api";
import { useAttachments, useAttachmentDelete } from "../hooks/use-attachments";
import type { AttachmentViewModel } from "../types/attachment-view-model";
import { isPreviewableAttachment } from "../types/attachment-view-model";

function formatFileSize(bytes: number) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function AttachmentRow({
  attachment,
  onPreview,
  onDownload,
  onDelete,
  isDeleting,
}: {
  attachment: AttachmentViewModel;
  onPreview: () => void;
  onDownload: () => void;
  onDelete: () => void;
  isDeleting: boolean;
}) {
  return (
    <div className="flex items-center justify-between gap-3 rounded-md border p-3 text-sm">
      <div className="min-w-0 flex-1">
        <p className="truncate font-medium">{attachment.filename}</p>
        <p className="text-xs text-muted-foreground">
          {formatFileSize(attachment.sizeBytes)}
          {attachment.uploadedBy ? ` · by ${attachment.uploadedBy.name}` : null}
        </p>
      </div>
      <div className="flex shrink-0 items-center gap-1">
        {attachment.permissions.canPreview && isPreviewableAttachment(attachment) ? (
          <button
            type="button"
            onClick={onPreview}
            className="inline-flex h-8 w-8 items-center justify-center rounded-md hover:bg-muted"
            aria-label={`Preview ${attachment.filename}`}
          >
            <Eye className="h-4 w-4" aria-hidden="true" />
          </button>
        ) : null}
        {attachment.permissions.canDownload ? (
          <button
            type="button"
            onClick={onDownload}
            className="inline-flex h-8 w-8 items-center justify-center rounded-md hover:bg-muted"
            aria-label={`Download ${attachment.filename}`}
          >
            <Download className="h-4 w-4" aria-hidden="true" />
          </button>
        ) : null}
        {attachment.permissions.canDelete ? (
          <button
            type="button"
            onClick={onDelete}
            disabled={isDeleting}
            className="inline-flex h-8 w-8 items-center justify-center rounded-md text-destructive hover:bg-destructive/10 disabled:opacity-50"
            aria-label={`Delete ${attachment.filename}`}
          >
            {isDeleting ? (
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            ) : (
              <Trash2 className="h-4 w-4" aria-hidden="true" />
            )}
          </button>
        ) : null}
      </div>
    </div>
  );
}

export function AttachmentList({ requisitionId }: { requisitionId: string }) {
  const { data: attachments, isLoading, isError } = useAttachments(requisitionId);
  const deleteMutation = useAttachmentDelete(requisitionId);
  const rightPanel = useRightPanel();
  const [downloadError, setDownloadError] = useState<string | null>(null);

  async function handleDownload(attachment: AttachmentViewModel) {
    setDownloadError(null);

    try {
      const blob = await downloadAttachmentBlob(attachment.id);
      const objectUrl = URL.createObjectURL(blob);
      const link = document.createElement("a");

      link.href = objectUrl;
      link.download = attachment.filename;
      link.rel = "noopener";
      document.body.appendChild(link);
      link.click();
      link.remove();

      URL.revokeObjectURL(objectUrl);
    } catch {
      setDownloadError(`Could not download ${attachment.filename}.`);
    }
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-6 text-sm text-muted-foreground">
        <Loader2 className="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />
        Loading attachments
      </div>
    );
  }

  if (isError) {
    return (
      <div className="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
        Could not load attachments.
      </div>
    );
  }

  if (!attachments || attachments.length === 0) {
    return (
      <p className="py-3 text-sm text-muted-foreground">
        No evidence files have been uploaded yet.
      </p>
    );
  }

  return (
    <div className="space-y-2">
      {downloadError ? (
        <div
          role="alert"
          className="flex items-center gap-2 rounded-md border border-red-300 bg-red-50 p-2 text-sm text-red-900"
        >
          <AlertCircle className="h-4 w-4" aria-hidden="true" />
          <span>{downloadError}</span>
        </div>
      ) : null}
      {attachments.map((attachment) => (
        <AttachmentRow
          key={attachment.id}
          attachment={attachment}
          onPreview={() =>
            rightPanel.openPanel({
              id: `attachment-preview-${attachment.id}`,
              title: attachment.filename,
              description: buildAttachmentDescription(attachment),
              size: "lg",
              content: <AttachmentPreviewPanel attachment={attachment} />,
            })
          }
          onDownload={() => void handleDownload(attachment)}
          onDelete={() => deleteMutation.mutate(attachment.id)}
          isDeleting={deleteMutation.isPending && deleteMutation.variables === attachment.id}
        />
      ))}
    </div>
  );
}

function buildAttachmentDescription(attachment: AttachmentViewModel) {
  const details = [formatFileSize(attachment.sizeBytes)];

  if (attachment.uploadedBy) {
    details.push(`Uploaded by ${attachment.uploadedBy.name}`);
  }

  return details.join(" · ");
}
