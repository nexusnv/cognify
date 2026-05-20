import { http, HttpResponse } from "msw";
import type { Attachment } from "@cognify/api-client/schemas";
import {
  appendVendorPortalQuotationAttachment,
  expiredVendorPortalToken,
  getVendorPortalQuotationFixture,
  unavailableVendorPortalToken,
  validVendorPortalToken,
  vendorPortalRfqInvitationFixture,
} from "./vendor-portal-fixtures";

export const vendorPortalHandlers = [
  http.get("/api/vendor-portal/rfq-invitations/:token", ({ params }) => {
    const token = String(params.token);

    if (token === validVendorPortalToken) {
      return HttpResponse.json({ data: structuredClone(vendorPortalRfqInvitationFixture) });
    }

    if (token === expiredVendorPortalToken) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "This vendor portal link has expired." } },
        { status: 409 },
      );
    }

    if (token === unavailableVendorPortalToken) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "This vendor portal link is no longer available." } },
        { status: 409 },
      );
    }

    return HttpResponse.json(
      { error: { code: "not_found", message: "This vendor portal link could not be found." } },
      { status: 404 },
    );
  }),

  http.get("/api/vendor-portal/rfq-invitations/:token/quotation", ({ params }) => {
    const token = String(params.token);

    if (token === validVendorPortalToken) {
      return HttpResponse.json({ data: structuredClone(getVendorPortalQuotationFixture()) });
    }

    if (token === expiredVendorPortalToken) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "This vendor portal link has expired." } },
        { status: 409 },
      );
    }

    if (token === unavailableVendorPortalToken) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "This vendor portal link is no longer available." } },
        { status: 409 },
      );
    }

    return HttpResponse.json(
      { error: { code: "not_found", message: "This vendor portal link could not be found." } },
      { status: 404 },
    );
  }),

  http.post("/api/vendor-portal/rfq-invitations/:token/quotation/attachments", async ({ request, params }) => {
    const token = String(params.token);

    if (token !== validVendorPortalToken) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "This vendor portal link could not be found." } },
        { status: 404 },
      );
    }

    const upload = await parseQuotationUpload(request);
    if (!upload) {
      return HttpResponse.json(
        {
          error: {
            code: "validation_failed",
            message: "File is required.",
            details: { fields: { file: ["File is required."] } },
            requestId: null,
          },
        },
        { status: 422 },
      );
    }

    const attachment = buildQuotationAttachment(upload);
    const quotation = appendVendorPortalQuotationAttachment(attachment);

    return HttpResponse.json({ data: structuredClone(quotation) }, { status: 201 });
  }),
];

async function parseQuotationUpload(request: Request) {
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
    const mimeType = formDataString(formData, "file.mimeType") ?? (file.type || "application/octet-stream");
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
  const content = contentStart >= 0 && contentEnd >= 0 ? body.slice(contentStart, contentEnd) : "";

  return {
    filename: filenameMatch[1],
    mimeType: mimeTypeValue,
    sizeBytes: content.length,
  };
}

function extractMultipartField(body: string, fieldName: string) {
  const fieldMatch = body.match(
    new RegExp(`name="${fieldName.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}"\\r?\\n\\r?\\n([^\\r\\n]+)`),
  );
  return fieldMatch?.[1]?.trim() ?? null;
}

function buildQuotationAttachment(upload: { filename: string; mimeType: string; sizeBytes: number }): Attachment {
  return {
    id: `quotation-att-${Date.now()}`,
    parentType: "quotation",
    parentId: "quotation-1",
    filename: upload.filename,
    mimeType: upload.mimeType,
    extension: upload.filename.split(".").pop() ?? null,
    sizeBytes: upload.sizeBytes,
    previewable: false,
    uploadedBy: null,
    createdAt: new Date().toISOString(),
    permissions: {
      canPreview: false,
      canDownload: false,
      canDelete: false,
    },
  };
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
