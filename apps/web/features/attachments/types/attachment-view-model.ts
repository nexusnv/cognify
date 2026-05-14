import type { Attachment } from "@cognify/api-client/schemas";

/**
 * View model alias for the generated Attachment type.
 * Generated types are used directly; add only view-state fields here.
 */
export type AttachmentViewModel = Attachment;

export const PREVIEWABLE_IMAGE_MIME_TYPES = ["image/png", "image/jpeg", "image/webp"] as const;

export const PREVIEWABLE_ATTACHMENT_MIME_TYPES = [
  "application/pdf",
  ...PREVIEWABLE_IMAGE_MIME_TYPES,
] as const;

export type PreviewableAttachmentMimeType = (typeof PREVIEWABLE_ATTACHMENT_MIME_TYPES)[number];

export function isPreviewableAttachment(attachment: AttachmentViewModel) {
  return (
    attachment.previewable &&
    PREVIEWABLE_ATTACHMENT_MIME_TYPES.includes(attachment.mimeType as PreviewableAttachmentMimeType)
  );
}
