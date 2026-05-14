import type { Attachment } from "@cognify/api-client/schemas";
import { AttachmentParentType } from "@cognify/api-client/schemas";

export const attachmentFixtures = [
  {
    id: "att-1",
    parentType: AttachmentParentType.requisition,
    parentId: "req-1",
    filename: "supplier-quote.pdf",
    mimeType: "application/pdf",
    extension: "pdf",
    sizeBytes: 65536,
    previewable: true,
    uploadedBy: { id: "user-1", name: "Maya Tan" },
    createdAt: "2026-05-13T10:30:00.000Z",
    permissions: {
      canPreview: true,
      canDownload: true,
      canDelete: true,
    },
  },
  {
    id: "att-2",
    parentType: AttachmentParentType.requisition,
    parentId: "req-1",
    filename: "spec-sheet.png",
    mimeType: "image/png",
    extension: "png",
    sizeBytes: 128000,
    previewable: true,
    uploadedBy: { id: "user-1", name: "Maya Tan" },
    createdAt: "2026-05-13T11:00:00.000Z",
    permissions: {
      canPreview: true,
      canDownload: true,
      canDelete: true,
    },
  },
  {
    id: "att-readonly",
    parentType: AttachmentParentType.requisition,
    parentId: "req-readonly",
    filename: "readonly-evidence.pdf",
    mimeType: "application/pdf",
    extension: "pdf",
    sizeBytes: 32768,
    previewable: true,
    uploadedBy: { id: "user-2", name: "Aisha Rahman" },
    createdAt: "2026-05-13T12:00:00.000Z",
    permissions: {
      canPreview: false,
      canDownload: false,
      canDelete: false,
    },
  },
] satisfies Attachment[];
