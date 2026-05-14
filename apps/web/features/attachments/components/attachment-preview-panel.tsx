"use client";

/* eslint-disable @next/next/no-img-element */

import { Loader2, TriangleAlert } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import type { AttachmentViewModel } from "../types/attachment-view-model";
import { PREVIEWABLE_IMAGE_MIME_TYPES } from "../types/attachment-view-model";
import { previewAttachmentBlob } from "../api/attachments-api";

interface AttachmentPreviewPanelProps {
  attachment: AttachmentViewModel;
}

export function AttachmentPreviewPanel({ attachment }: AttachmentPreviewPanelProps) {
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const previewMode = useMemo(() => {
    if (attachment.mimeType === "application/pdf") return "pdf";
    if (
      PREVIEWABLE_IMAGE_MIME_TYPES.includes(
        attachment.mimeType as (typeof PREVIEWABLE_IMAGE_MIME_TYPES)[number],
      )
    ) {
      return "image";
    }

    return null;
  }, [attachment.mimeType]);

  useEffect(() => {
    let active = true;
    let objectUrl: string | null = null;

    async function loadPreview() {
      if (!previewMode) {
        setIsLoading(false);
        setPreviewUrl(null);
        setError("Preview not available for this file type.");
        return;
      }

      setIsLoading(true);
      setError(null);

      try {
        const blob = await previewAttachmentBlob(attachment.id);
        objectUrl = URL.createObjectURL(blob);

        if (!active) {
          URL.revokeObjectURL(objectUrl);
          return;
        }

        setPreviewUrl(objectUrl);
        setIsLoading(false);
      } catch {
        if (!active) return;
        setError("Preview could not be loaded.");
        setIsLoading(false);
      }
    }

    loadPreview();

    return () => {
      active = false;

      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
      }
    };
  }, [attachment.id, previewMode]);

  if (isLoading) {
    return (
      <div className="flex h-[60vh] items-center justify-center rounded-md border bg-background text-sm text-muted-foreground">
        <Loader2 className="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />
        Loading preview
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex h-[60vh] items-center justify-center rounded-md border bg-muted/20 p-4 text-sm text-muted-foreground">
        <TriangleAlert className="mr-2 h-4 w-4" aria-hidden="true" />
        {error}
      </div>
    );
  }

  if (!previewUrl || !previewMode) {
    return null;
  }

  if (previewMode === "pdf") {
    return (
      <div className="h-[60vh] overflow-hidden rounded-md border bg-background">
        <iframe
          src={previewUrl}
          title={`Preview of ${attachment.filename}`}
          className="h-full w-full"
          sandbox="allow-scripts allow-same-origin"
        />
      </div>
    );
  }

  return (
    <div className="flex h-[60vh] items-center justify-center overflow-hidden rounded-md border bg-muted/20 p-4">
      <img
        src={previewUrl}
        alt={`Preview of ${attachment.filename}`}
        className="max-h-full max-w-full object-contain"
      />
    </div>
  );
}
