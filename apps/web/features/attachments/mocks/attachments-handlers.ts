import { http, HttpResponse } from "msw";
import type { Attachment } from "@cognify/api-client/schemas";
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
    parentType: "requisition",
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
  if (formDataUpload && isHelpfulFilename(formDataUpload.filename)) return formDataUpload;

  const textUpload = parseMultipartUploadText(await request.clone().text());
  if (formDataUpload && textUpload?.filename) {
    return {
      filename: textUpload.filename,
      mimeType: formDataUpload.mimeType,
      sizeBytes: formDataUpload.sizeBytes,
    };
  }

  return formDataUpload ?? textUpload;
}

async function parseFormDataUpload(request: Request) {
  try {
    const formData = await request.formData();
    const file = formData.get("file");
    if (!isFileLike(file)) return null;
    const filename = formDataString(formData, "file.filename") ?? file.name;
    const mimeType =
      formDataString(formData, "file.mimeType") ?? (file.type || "application/octet-stream");
    const metadataSize = formDataString(formData, "file.sizeBytes");
    const sizeBytes = metadataSize ? Number(metadataSize) : file.size;

    return {
      filename,
      mimeType,
      sizeBytes,
    };
  } catch {
    return null;
  }
}

function formDataString(formData: FormData, key: string): string | null {
  const value = formData.get(key);

  return typeof value === "string" ? value : null;
}

function isHelpfulFilename(filename: string | null | undefined) {
  return Boolean(filename && filename !== "blob");
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
  const filename =
    extractMultipartField(body, "file.filename") ?? extractMultipartField(body, "filename");
  const mimeType =
    extractMultipartField(body, "file.mimeType") ?? extractMultipartField(body, "mimeType");
  const sizeBytes =
    extractMultipartField(body, "file.sizeBytes") ?? extractMultipartField(body, "sizeBytes");

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
  const nameIndex = body.indexOf(`name="${fieldName}"`);
  if (nameIndex < 0) return null;

  const crlfValueStart = body.indexOf("\r\n\r\n", nameIndex);
  const lfValueStart = body.indexOf("\n\n", nameIndex);
  const valueStart =
    crlfValueStart >= 0 && (lfValueStart < 0 || crlfValueStart < lfValueStart)
      ? crlfValueStart + 4
      : lfValueStart >= 0
        ? lfValueStart + 2
        : -1;
  if (valueStart < 0) return null;

  const boundaryLine = body.split(/\r?\n/, 1)[0] ?? "";
  const valueEnd = boundaryLine ? body.indexOf(boundaryLine, valueStart) : -1;
  const value = valueEnd >= 0 ? body.slice(valueStart, valueEnd) : body.slice(valueStart);

  return value.trim() || null;
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
