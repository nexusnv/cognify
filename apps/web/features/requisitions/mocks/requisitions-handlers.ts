import { http, HttpResponse } from "msw";
import type { AuditEvent } from "@cognify/api-client/schemas";
import { calculateEstimatedTotal } from "../utils/requisition-totals";
import {
  requisitionActivityFixtures,
  requisitionFixtures,
  requisitionIntakeOptionsFixture,
  requisitionItemSuggestionFixtures,
  requisitionTemplateFixtures,
} from "./requisitions-fixtures";
import type {
  Requisition,
  RequisitionFormValues,
  RequisitionTemplate,
} from "../types/requisition-view-model";

let requisitions = [...requisitionFixtures];
let activity = structuredClone(requisitionActivityFixtures);
let requisitionSequence = requisitionFixtures.length;
let activitySequence = 0;

export function resetRequisitionMockState() {
  requisitions = [...requisitionFixtures];
  activity = structuredClone(requisitionActivityFixtures);
  requisitionSequence = requisitionFixtures.length;
  activitySequence = 0;
}

export const requisitionsHandlers = [
  http.get("/api/requisition-templates", () => {
    return HttpResponse.json({ data: requisitionTemplateFixtures });
  }),

  http.get("/api/requisition-line-item-suggestions", ({ request }) => {
    const url = new URL(request.url);
    const search = url.searchParams.get("search")?.toLowerCase();
    const category = url.searchParams.get("category");
    const currency = url.searchParams.get("currency")?.toUpperCase();

    const data = requisitionItemSuggestionFixtures.filter((suggestion) => {
      const matchesSearch =
        !search ||
        suggestion.name.toLowerCase().includes(search) ||
        suggestion.category?.toLowerCase().includes(search) ||
        suggestion.unit.toLowerCase().includes(search);
      const matchesCategory = !category || suggestion.category === category;
      const matchesCurrency = !currency || suggestion.currency === currency;

      return matchesSearch && matchesCategory && matchesCurrency;
    });

    return HttpResponse.json({ data });
  }),

  http.get("/api/requisition-intake-options", () => {
    return HttpResponse.json({ data: requisitionIntakeOptionsFixture });
  }),

  http.get("/api/requisitions", ({ request }) => {
    const url = new URL(request.url);
    const search = url.searchParams.get("search")?.toLowerCase();
    const status = url.searchParams.get("status");
    const data = requisitions.filter((requisition) => {
      const matchesSearch =
        !search ||
        requisition.title.toLowerCase().includes(search) ||
        requisition.number.toLowerCase().includes(search);
      const matchesStatus = !status || requisition.status === status;

      return matchesSearch && matchesStatus;
    });

    return HttpResponse.json({
      data,
      meta: {
        currentPage: 1,
        perPage: 25,
        total: data.length,
        lastPage: 1,
      },
    });
  }),

  http.post("/api/requisitions", async ({ request }) => {
    const values = (await request.json()) as RequisitionFormValues;

    if (!values.title?.trim()) {
      return HttpResponse.json(
        {
          error: {
            code: "validation_failed",
            message: "Validation failed",
            details: {
              fields: { title: ["Title is required."] },
            },
          },
        },
        { status: 422 },
      );
    }

    const id = `req-${Date.now()}`;
    const requisition = buildRequisition(values, id, "draft", nextRequisitionNumber(), undefined, 0);

    requisitions = [requisition, ...requisitions];
    activity[requisition.id] = [
      buildActivityEvent("requisition.created", "Draft created", requisition, requisition.createdAt),
    ];

    return HttpResponse.json({ data: requisition }, { status: 201 });
  }),

  http.get("/api/requisitions/:requisitionId", ({ params }) => {
    const requisition = requisitions.find((item) => item.id === params.requisitionId);

    if (!requisition) {
      return HttpResponse.json(
        { message: "Requisition not found", code: "not_found" },
        { status: 404 },
      );
    }

    return HttpResponse.json({ data: requisition });
  }),

  http.patch("/api/requisitions/:requisitionId", async ({ params, request }) => {
    const values = (await request.json()) as RequisitionFormValues & { lockVersion?: number };
    const existing = requisitions.find((item) => item.id === params.requisitionId);

    if (!existing) {
      return HttpResponse.json(
        { message: "Requisition not found", code: "not_found" },
        { status: 404 },
      );
    }

    if (existing.status !== "draft") {
      return HttpResponse.json(
        { message: "Submitted requisitions cannot be edited.", code: "invalid_state" },
        { status: 409 },
      );
    }

    if (typeof values.lockVersion !== "number") {
      return HttpResponse.json(
        {
          error: {
            code: "validation_failed",
            message: "Validation failed",
            details: {
              fields: { lockVersion: ["The lock version field is required."] },
            },
          },
        },
        { status: 422 },
      );
    }

    if (values.lockVersion !== existing.lockVersion) {
      return HttpResponse.json(
        { error: { code: "draft_conflict", message: "The draft has changed since it was loaded." } },
        { status: 409 },
      );
    }

    const updated = buildRequisition(
      values,
      existing.id,
      "draft",
      existing.number,
      existing.createdAt,
      existing.lockVersion + 1,
    );
    requisitions = requisitions.map((item) => (item.id === existing.id ? updated : item));
    activity[updated.id] = [
      ...(activity[updated.id] ?? []),
      buildActivityEvent(
        "requisition.updated",
        "Draft updated",
        updated,
        updated.updatedAt,
        existing,
        updated,
      ),
    ];

    return HttpResponse.json({ data: updated });
  }),

  http.post("/api/requisitions/:requisitionId/apply-template", async ({ params, request }) => {
    const body = (await request.json()) as {
      templateId?: string;
      mode?: "fill-empty" | "replace";
      lockVersion?: number;
    };
    const existing = requisitions.find((item) => item.id === params.requisitionId);

    if (!existing) {
      return HttpResponse.json(
        { message: "Requisition not found", code: "not_found" },
        { status: 404 },
      );
    }

    if (existing.status !== "draft") {
      return HttpResponse.json(
        { message: "Only draft requisitions can receive templates.", code: "invalid_state" },
        { status: 409 },
      );
    }

    if (typeof body.lockVersion !== "number") {
      return HttpResponse.json(
        {
          error: {
            code: "validation_failed",
            message: "Validation failed",
            details: {
              fields: { lockVersion: ["The lock version field is required."] },
            },
          },
        },
        { status: 422 },
      );
    }

    if (body.lockVersion !== existing.lockVersion) {
      return HttpResponse.json(
        { error: { code: "draft_conflict", message: "The draft has changed since it was loaded." } },
        { status: 409 },
      );
    }

    const template = requisitionTemplateFixtures.find((item) => item.id === body.templateId);
    if (!template || !body.mode) {
      return HttpResponse.json(
        { message: "Template not found", code: "not_found" },
        { status: 404 },
      );
    }

    const mergedValues = mergeTemplateValues(toFormValues(existing), template, body.mode);
    const updated = buildRequisition(
      mergedValues,
      existing.id,
      "draft",
      existing.number,
      existing.createdAt,
      existing.lockVersion + 1,
    );

    requisitions = requisitions.map((item) => (item.id === existing.id ? updated : item));
    activity[updated.id] = [
      ...(activity[updated.id] ?? []),
      buildActivityEvent(
        "requisition.template_applied",
        "Template applied",
        updated,
        updated.updatedAt,
        existing,
        updated,
      ),
    ];

    return HttpResponse.json({ data: updated });
  }),

  http.post("/api/requisitions/:requisitionId/submit", ({ params }) => {
    const existing = requisitions.find((item) => item.id === params.requisitionId);

    if (!existing) {
      return HttpResponse.json(
        { message: "Requisition not found", code: "not_found" },
        { status: 404 },
      );
    }

    if (existing.status !== "draft") {
      return HttpResponse.json(
        { message: "Only draft requisitions can be submitted.", code: "invalid_state" },
        { status: 409 },
      );
    }

    const submittedAt = new Date().toISOString();
    const submitted: Requisition = {
      ...existing,
      status: "submitted",
      updatedAt: submittedAt,
      submittedAt,
      permissions: {
        ...existing.permissions,
        canUpdate: false,
        canSubmit: false,
      },
    };
    requisitions = requisitions.map((item) => (item.id === existing.id ? submitted : item));
    activity[submitted.id] = [
      ...(activity[submitted.id] ?? []),
      buildActivityEvent(
        "requisition.submitted",
        "Submitted for review",
        submitted,
        submittedAt,
        existing,
        submitted,
      ),
    ];

    return HttpResponse.json({ data: submitted });
  }),

  http.get("/api/requisitions/:requisitionId/activity", ({ params }) => {
    return HttpResponse.json({ data: activity[String(params.requisitionId)] ?? [] });
  }),
];

function toFormValues(requisition: Requisition): RequisitionFormValues {
  return {
    title: requisition.title,
    businessJustification: requisition.businessJustification,
    neededByDate: requisition.neededByDate,
    department: requisition.department ?? "",
    projectId: requisition.projectId ?? "",
    costCenter: requisition.costCenter ?? "",
    deliveryLocation: requisition.deliveryLocation ?? "",
    currency: requisition.currency ?? "MYR",
    lineItems: requisition.lineItems,
  };
}

function mergeTemplateValues(
  current: RequisitionFormValues,
  template: RequisitionTemplate,
  mode: "fill-empty" | "replace",
): RequisitionFormValues {
  const defaults = template.defaults;
  const templateLineItems = defaults.lineItems?.length
    ? defaults.lineItems.map((lineItem) => ({
        id: lineItem.id,
        name: lineItem.name,
        description: lineItem.description,
        quantity: lineItem.quantity ?? 1,
        unit: lineItem.unit ?? "each",
        estimatedUnitPrice: lineItem.estimatedUnitPrice ?? 0,
        currency: lineItem.currency ?? defaults.currency ?? current.currency ?? "MYR",
        estimatedLineTotal: lineItem.estimatedLineTotal,
      }))
    : current.lineItems;

  if (mode === "replace") {
    return {
      title: defaults.title ?? current.title,
      businessJustification: defaults.businessJustification ?? current.businessJustification,
      neededByDate: defaults.neededByDate ?? current.neededByDate,
      department: defaults.department ?? current.department,
      projectId: defaults.projectId ?? current.projectId,
      costCenter: defaults.costCenter ?? current.costCenter,
      deliveryLocation: defaults.deliveryLocation ?? current.deliveryLocation,
      currency: defaults.currency ?? current.currency,
      lineItems: templateLineItems,
    };
  }

  return {
    ...current,
    title: current.title || defaults.title || "",
    businessJustification: current.businessJustification || defaults.businessJustification || "",
    neededByDate: current.neededByDate || defaults.neededByDate || "",
    department: current.department || defaults.department || "",
    projectId: current.projectId || defaults.projectId || "",
    costCenter: current.costCenter || defaults.costCenter || "",
    deliveryLocation: current.deliveryLocation || defaults.deliveryLocation || "",
    currency: current.currency || defaults.currency || "MYR",
    lineItems:
      current.lineItems.length === 1 && isBlankLineItem(current.lineItems[0]) && templateLineItems.length
        ? templateLineItems
        : current.lineItems,
  };
}

function isBlankLineItem(lineItem: RequisitionFormValues["lineItems"][number]): boolean {
  return (
    !lineItem.name.trim() &&
    !lineItem.description?.trim() &&
    lineItem.quantity === 1 &&
    lineItem.unit === "each" &&
    lineItem.estimatedUnitPrice === 0
  );
}

function buildRequisition(
  values: RequisitionFormValues,
  id: string,
  status: Requisition["status"],
  number: string,
  createdAt?: string,
  lockVersion = 0,
): Requisition {
  const totals = calculateEstimatedTotal(values.lineItems);
  const now = new Date().toISOString();

  return {
    id,
    number,
    tenantId: "tenant-1",
    title: values.title,
    status,
    lockVersion,
    businessJustification: values.businessJustification,
    neededByDate: values.neededByDate,
    department: values.department,
    projectId: values.projectId,
    costCenter: values.costCenter,
    deliveryLocation: values.deliveryLocation,
    currency: totals.currency,
    estimatedTotal: totals.estimatedTotal,
    requester: {
      id: "user-1",
      name: "Maya Tan",
      email: "maya.tan@acme.test",
    },
    lineItems: values.lineItems.map((item, index) => ({
      ...item,
      id: item.id ?? `line-${id}-${index + 1}`,
      estimatedLineTotal: totals.lineTotals[index],
    })),
    createdAt: createdAt ?? now,
    updatedAt: now,
    submittedAt: status === "submitted" ? now : null,
    permissions: {
      canUpdate: status === "draft",
      canSubmit: status === "draft",
      canViewActivity: true,
    },
  };
}

function nextRequisitionNumber() {
  requisitionSequence += 1;
  return `REQ-2026-${String(requisitionSequence).padStart(6, "0")}`;
}

function buildActivityEvent(
  action: string,
  message: string,
  requisition: Requisition,
  occurredAt: string,
  before: Requisition | null = null,
  after: Requisition | null = null,
): AuditEvent {
  return {
    id: `activity-${Date.now()}-${++activitySequence}`,
    action,
    message,
    actor: requisition.requester,
    subject: {
      type: "requisition",
      id: requisition.id,
      display: requisition.number,
    },
    metadata: {
      status: requisition.status,
    },
    before,
    after,
    occurredAt,
    requestId: null,
  };
}
