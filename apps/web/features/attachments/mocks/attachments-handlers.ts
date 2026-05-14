import { http, HttpResponse } from "msw";
import type { Attachment } from "@cognify/api-client/schemas";
import { AttachmentParentType } from "@cognify/api-client/schemas";
import { attachmentFixtures } from "./attachments-fixtures";

let attachments: Attachment[] = structuredClone(attachmentFixtures);
let attachmentSequence = attachmentFixtures.length;

const allowedMimeTypes = new Set([
  "application/pdf",
  "image/png",
  "image/jpeg",
  "image/webp",
  "text/plain",
  "text/csv",
  "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
  "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
]);

const previewableMimeTypes = new Set(["application/pdf", "image/png", "image/jpeg", "image/webp"]);

export function resetAttachmentMockState() {
  attachments = structuredClone(attachmentFixtures);
  attachmentSequence = attachmentFixtures.length;
}

export const attachmentHandlers = [
  http.get("/api/requisitions/:requisitionId/attachments", ({ params }) => {
    const requisitionId = String(params.requisitionId);
    const data = attachments.filter((attachment) => attachment.parentId === requisitionId);

    return HttpResponse.json({ data });
  }),

  http.post("/api/requisitions/:requisitionId/attachments", async ({ request, params }) => {
    const requisitionId = String(params.requisitionId);
    const upload = await parseMultipartUpload(request);

    if (!upload) {
      return validationError("File is required.");
    }

    if (!allowedMimeTypes.has(upload.mimeType)) {
      return validationError("File type not supported.");
    }

    attachmentSequence += 1;
    const attachment = buildAttachment(requisitionId, upload, attachmentSequence);
    attachments = [attachment, ...attachments];

    return HttpResponse.json({ data: attachment }, { status: 201 });
  }),

  http.get("/api/attachments/:attachmentId/preview", ({ params }) => {
    const attachment = findAttachment(String(params.attachmentId));

    if (!attachment) {
      return notFound();
    }

    if (!previewableMimeTypes.has(attachment.mimeType)) {
      return validationError("Preview is not supported for this file type.");
    }

    return HttpResponse.arrayBuffer(new ArrayBuffer(0), {
      status: 200,
      headers: {
        "Content-Type": attachment.mimeType,
        "Content-Disposition": `inline; filename="${attachment.filename}"`,
      },
    });
  }),

  http.get("/api/attachments/:attachmentId/download", ({ params }) => {
    const attachment = findAttachment(String(params.attachmentId));

    if (!attachment) {
      return notFound();
    }

    return HttpResponse.arrayBuffer(new ArrayBuffer(0), {
      status: 200,
      headers: {
        "Content-Type": attachment.mimeType,
        "Content-Disposition": `attachment; filename="${attachment.filename}"`,
      },
    });
  }),

  http.delete("/api/attachments/:attachmentId", ({ params }) => {
    const attachmentId = String(params.attachmentId);
    const index = attachments.findIndex((attachment) => attachment.id === attachmentId);

    if (index === -1) {
      return notFound();
    }

    attachments.splice(index, 1);

    return new HttpResponse(null, { status: 204 });
  }),
];

function buildAttachment(
  requisitionId: string,
  upload: { filename: string; mimeType: string; sizeBytes: number },
  sequence: number,
): Attachment {
  const previewable = previewableMimeTypes.has(upload.mimeType);

  return {
    id: `att-${sequence}`,
    parentType: AttachmentParentType.requisition,
    parentId: requisitionId,
    filename: upload.filename,
    mimeType: upload.mimeType,
    extension: upload.filename.split(".").pop() ?? null,
    sizeBytes: upload.sizeBytes,
    previewable,
    uploadedBy: {
      id: "user-1",
      name: "Maya Tan",
    },
    createdAt: new Date().toISOString(),
    permissions: {
      canPreview: previewable,
      canDownload: true,
      canDelete: true,
    },
  };
}

function findAttachment(attachmentId: string) {
  return attachments.find((attachment) => attachment.id === attachmentId);
}

async function parseMultipartUpload(request: Request) {
  const formDataUpload = await parseFormDataUpload(request.clone());
  if (formDataUpload) return formDataUpload;

  return parseMultipartUploadText(await request.text());
}

async function parseFormDataUpload(request: Request) {
  try {
    const formData = await request.formData();
    const file = formData.get("file");
    if (!isFileLike(file)) return null;

    return {
      filename: file.name,
      mimeType: file.type || "application/octet-stream",
      sizeBytes: file.size,
    };
  } catch {
    return null;
  }
}

function isFileLike(value: FormDataEntryValue | null): value is File {
  return (
    typeof value === "object" &&
    value !== null &&
    "name" in value &&
    "type" in value &&
    "size" in value
  );
}

function parseMultipartUploadText(body: string) {
  const filename = extractMultipartField(body, "filename");
  const mimeType = extractMultipartField(body, "mimeType");
  const sizeBytes = extractMultipartField(body, "sizeBytes");

  if (filename && mimeType && sizeBytes) {
    return {
      filename,
      mimeType,
      sizeBytes: Number(sizeBytes),
    };
  }

  const filenameMatch = body.match(/filename="([^"]+)"/);
  if (!filenameMatch) return null;

  const mimeTypeMatch = body.match(/Content-Type:\s*([^\r\n]+)/);
  const mimeTypeValue = mimeTypeMatch?.[1]?.trim() ?? "application/octet-stream";

  const lines = body.split(/\r?\n/);
  const boundaryLine = lines[0] ?? "";
  const headerBreak = body.match(/\r?\n\r?\n/);
  const contentStart = headerBreak ? body.indexOf(headerBreak[0]) + headerBreak[0].length : -1;
  const contentEnd = boundaryLine ? body.indexOf(boundaryLine, contentStart) : -1;
  const content =
    contentStart >= 0 && contentEnd > contentStart
      ? body.slice(contentStart, contentEnd).replace(/\r?\n$/, "")
      : "";

  return {
    filename: filenameMatch[1],
    mimeType: mimeTypeValue,
    sizeBytes: Buffer.byteLength(content, "utf8"),
  };
}

function extractMultipartField(body: string, fieldName: string) {
  const match = body.match(
    new RegExp(`name="${fieldName}"\\r?\\n\\r?\\n([\\s\\S]*?)(?:\\r?\\n--|$)`),
  );

  return match?.[1]?.trim() ?? null;
}

function validationError(message: string) {
  return HttpResponse.json(
    {
      error: {
        code: "validation_failed",
        message,
        details: { fields: { file: [message] } },
        requestId: null,
      },
    },
    { status: 422 },
  );
}

function notFound() {
  return HttpResponse.json(
    {
      error: {
        code: "not_found",
        message: "Attachment not found.",
        details: {},
        requestId: null,
      },
    },
    { status: 404 },
  );
}
