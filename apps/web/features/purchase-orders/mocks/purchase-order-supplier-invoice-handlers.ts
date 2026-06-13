import { http, HttpResponse } from "msw";
import type {
  Attachment,
  CaptureSupplierInvoiceRequest,
  SupplierInvoice,
} from "@cognify/api-client/schemas";
import { getPurchaseOrderMockState, updatePurchaseOrderMockState } from "./purchase-order-handlers";
import {
  buildSupplierInvoiceAttachmentFixture,
  buildSupplierInvoiceFixture,
} from "./purchase-order-supplier-invoice-fixtures";

type InvoiceMap = Record<string, SupplierInvoice[]>;
type AttachmentMap = Record<string, Attachment[]>;

let invoicesByPurchaseOrder: InvoiceMap = {};
let attachmentsByInvoice: AttachmentMap = {};
let createdInvoiceCount = 0;
let createdAttachmentCount = 0;

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

export function resetSupplierInvoiceMockState() {
  invoicesByPurchaseOrder = {};
  attachmentsByInvoice = {};
  createdInvoiceCount = 0;
  createdAttachmentCount = 0;
}

export function setSupplierInvoiceMockState(purchaseOrderId: string, nextInvoices: SupplierInvoice[]) {
  invoicesByPurchaseOrder[purchaseOrderId] = nextInvoices.map((invoice) => structuredClone(invoice));
  createdInvoiceCount = Math.max(createdInvoiceCount, nextInvoices.length);
}

export function setSupplierInvoiceAttachmentsMockState(supplierInvoiceId: string, nextAttachments: Attachment[]) {
  attachmentsByInvoice[supplierInvoiceId] = nextAttachments.map((attachment) => structuredClone(attachment));
  createdAttachmentCount = Math.max(createdAttachmentCount, nextAttachments.length);
}

function listInvoices(purchaseOrderId: string) {
  return invoicesByPurchaseOrder[purchaseOrderId] ?? [];
}

function findInvoice(supplierInvoiceId: string) {
  for (const invoices of Object.values(invoicesByPurchaseOrder)) {
    const invoice = invoices.find((item) => item.id === supplierInvoiceId);
    if (invoice) return invoice;
  }

  return null;
}

function conflictResponse(message: string) {
  return HttpResponse.json(
    { error: { code: "invalid_state", message } },
    { status: 409 },
  );
}

function validationFailedResponse(message: string, fields: Record<string, string[]>) {
  return HttpResponse.json(
    {
      error: {
        code: "validation_failed",
        message,
        details: { fields },
      },
    },
    { status: 422 },
  );
}

function missingTenantResponse(request: Request) {
  if (request.headers.get("x-tenant-id")) {
    return null;
  }

  return HttpResponse.json(
    { error: { code: "ambiguous_tenant", message: "Tenant context is required." } },
    { status: 400 },
  );
}

export const purchaseOrderSupplierInvoiceHandlers = [
  http.get("/api/purchase-orders/:purchaseOrder/supplier-invoices", ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    return HttpResponse.json({
      data: {
        data: listInvoices(String(params.purchaseOrder)),
      },
    });
  }),

  http.post("/api/purchase-orders/:purchaseOrder/supplier-invoices", async ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const purchaseOrderId = String(params.purchaseOrder);
    const purchaseOrder = getPurchaseOrderMockState(purchaseOrderId);

    if (!purchaseOrder) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Purchase order not found." } },
        { status: 404 },
      );
    }

    const body = (await request.json()) as CaptureSupplierInvoiceRequest;

    if (body.lockVersion !== purchaseOrder.lockVersion) {
      return conflictResponse("The purchase order has changed. Reload and try again.");
    }

    const duplicate = listInvoices(purchaseOrderId).find(
      (invoice) => invoice.invoiceNumber.toLowerCase() === body.invoiceNumber.toLowerCase(),
    );
    if (duplicate) {
      return conflictResponse(`Supplier invoice ${body.invoiceNumber} already exists for this purchase order.`);
    }

    if (body.lines.length === 0) {
      return validationFailedResponse("At least one invoice line is required.", {
        lines: ["At least one invoice line is required."],
      });
    }

    const zeroQuantityLine = body.lines.find((line) => Number(line.quantityInvoiced) <= 0);
    if (zeroQuantityLine) {
      return validationFailedResponse("Invoice line quantities must be greater than zero.", {
        lines: ["Invoice line quantities must be greater than zero."],
      });
    }

    const invalidLine = body.lines.find(
      (line) => !purchaseOrder.lines.some((purchaseOrderLine) => purchaseOrderLine.id === line.purchaseOrderLineId),
    );
    if (invalidLine) {
      return validationFailedResponse("Invoice lines must reference purchase order lines.", {
        lines: ["Invoice lines must reference purchase order lines."],
      });
    }

    createdInvoiceCount += 1;
    const lineSubtotal = body.lines.reduce(
      (sum, line) => sum + Number(line.quantityInvoiced) * Number(line.unitPrice),
      0,
    );
    const taxAmount = Number(body.taxAmount ?? 0);
    const freightAmount = Number(body.freightAmount ?? 0);
    const newInvoice = buildSupplierInvoiceFixture({
      id: `supplier-invoice-${createdInvoiceCount}`,
      purchaseOrderId,
      number: `SI-2026-${String(createdInvoiceCount).padStart(6, "0")}`,
      invoiceNumber: body.invoiceNumber,
      invoiceDate: body.invoiceDate,
      dueDate: body.dueDate ?? null,
      currency: purchaseOrder.currency,
      subtotalAmount: lineSubtotal.toFixed(2),
      taxAmount: taxAmount.toFixed(2),
      freightAmount: freightAmount.toFixed(2),
      totalAmount: (lineSubtotal + taxAmount + freightAmount).toFixed(2),
      notes: body.notes ?? null,
      capturedByUserId: "user-1",
      capturedAt: new Date().toISOString(),
      lines: body.lines.map((line, index) => {
        const purchaseOrderLine = purchaseOrder.lines.find((item) => item.id === line.purchaseOrderLineId);

        return {
          id: `supplier-invoice-line-${createdInvoiceCount}-${index + 1}`,
          purchaseOrderLineId: line.purchaseOrderLineId,
          lineNumber: purchaseOrderLine?.lineNumber ?? index + 1,
          descriptionSnapshot: purchaseOrderLine?.description ?? `Line ${index + 1}`,
          quantityOrdered: purchaseOrderLine?.quantity ?? "0.0000",
          quantityInvoiced: line.quantityInvoiced,
          unitPrice: line.unitPrice,
          lineSubtotal: (Number(line.quantityInvoiced) * Number(line.unitPrice)).toFixed(2),
          notes: line.notes ?? null,
        };
      }),
    });

    invoicesByPurchaseOrder[purchaseOrderId] = [...listInvoices(purchaseOrderId), newInvoice];
    syncPurchaseOrderInvoiceSummary(purchaseOrderId);

    return HttpResponse.json({ data: { data: newInvoice } }, { status: 201 });
  }),

  http.get("/api/supplier-invoices/:supplierInvoice/attachments", ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const supplierInvoiceId = String(params.supplierInvoice);
    const invoice = findInvoice(supplierInvoiceId);

    if (!invoice) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Supplier invoice not found." } },
        { status: 404 },
      );
    }

    return HttpResponse.json({
      data: {
        data: attachmentsByInvoice[supplierInvoiceId] ?? [],
      },
    });
  }),

  http.post("/api/supplier-invoices/:supplierInvoice/attachments", async ({ params, request }) => {
    const missingTenant = missingTenantResponse(request);
    if (missingTenant) return missingTenant;

    const supplierInvoiceId = String(params.supplierInvoice);
    const invoice = findInvoice(supplierInvoiceId);

    if (!invoice) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Supplier invoice not found." } },
        { status: 404 },
      );
    }

    const upload = await parseMultipartUpload(request);
    if (!upload) {
      return validationFailedResponse("File is required.", {
        file: ["File is required."],
      });
    }

    if (!allowedMimeTypes.has(upload.mimeType)) {
      return validationFailedResponse("File type not supported.", {
        file: ["File type not supported."],
      });
    }

    createdAttachmentCount += 1;
    const attachment = buildSupplierInvoiceAttachmentFixture({
      id: `supplier-invoice-attachment-${createdAttachmentCount}`,
      parentId: supplierInvoiceId,
      filename: upload.filename,
      mimeType: upload.mimeType,
      extension: upload.filename.split(".").pop() ?? null,
      sizeBytes: upload.sizeBytes,
      previewable: upload.mimeType === "application/pdf" || upload.mimeType.startsWith("image/"),
      createdAt: new Date().toISOString(),
    });

    attachmentsByInvoice[supplierInvoiceId] = [attachment, ...(attachmentsByInvoice[supplierInvoiceId] ?? [])];

    return HttpResponse.json({ data: { data: attachment } }, { status: 201 });
  }),
];

function syncPurchaseOrderInvoiceSummary(purchaseOrderId: string) {
  const invoices = listInvoices(purchaseOrderId);
  const latestInvoiceDate = invoices
    .map((invoice) => invoice.invoiceDate)
    .sort((left, right) => right.localeCompare(left))[0] ?? null;
  const totalInvoicedAmount = invoices
    .reduce((sum, invoice) => sum + Number(invoice.totalAmount), 0)
    .toFixed(2);

  updatePurchaseOrderMockState(purchaseOrderId, (purchaseOrder) => ({
    ...purchaseOrder,
    invoiceSummary: {
      totalInvoiceCount: invoices.length,
      latestInvoiceDate,
      totalInvoicedAmount,
      currency: invoices[0]?.currency ?? purchaseOrder.currency,
    },
  }));
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

    return {
      filename: file.name,
      mimeType: file.type || "application/octet-stream",
      sizeBytes: file.size,
    };
  } catch {
    return null;
  }
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

  return {
    filename: filenameMatch[1],
    mimeType: mimeTypeValue,
    sizeBytes: Number(extractMultipartField(body, "file.sizeBytes") ?? 0),
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
