import { http, HttpResponse } from "msw";
import { calculateEstimatedTotal } from "../utils/requisition-totals";
import { requisitionActivityFixtures, requisitionFixtures } from "./requisitions-fixtures";
import type { Requisition, RequisitionFormValues } from "../types/requisition-view-model";

let requisitions = [...requisitionFixtures];
const activity = { ...requisitionActivityFixtures };

export const requisitionsHandlers = [
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
    const requisition = buildRequisition(values, `req-${Date.now()}`, "draft");

    requisitions = [requisition, ...requisitions];
    activity[requisition.id] = [
      {
        id: `activity-${Date.now()}`,
        type: "requisition.created",
        message: "Draft created",
        actor: requisition.requester,
        occurredAt: requisition.createdAt,
      },
    ];

    return HttpResponse.json({ data: requisition }, { status: 201 });
  }),

  http.get("/api/requisitions/:requisitionId", ({ params }) => {
    const requisition = requisitions.find((item) => item.id === params.requisitionId);

    if (!requisition) {
      return HttpResponse.json({ message: "Requisition not found", code: "not_found" }, { status: 404 });
    }

    return HttpResponse.json({ data: requisition });
  }),

  http.patch("/api/requisitions/:requisitionId", async ({ params, request }) => {
    const values = (await request.json()) as RequisitionFormValues;
    const existing = requisitions.find((item) => item.id === params.requisitionId);

    if (!existing) {
      return HttpResponse.json({ message: "Requisition not found", code: "not_found" }, { status: 404 });
    }

    if (existing.status !== "draft") {
      return HttpResponse.json(
        { message: "Submitted requisitions cannot be edited.", code: "invalid_state" },
        { status: 409 },
      );
    }

    const updated = buildRequisition(values, existing.id, "draft", existing.number);
    requisitions = requisitions.map((item) => (item.id === existing.id ? updated : item));
    activity[updated.id] = [
      ...(activity[updated.id] ?? []),
      {
        id: `activity-${Date.now()}`,
        type: "requisition.updated",
        message: "Draft updated",
        actor: updated.requester,
        occurredAt: updated.updatedAt,
      },
    ];

    return HttpResponse.json({ data: updated });
  }),

  http.post("/api/requisitions/:requisitionId/submit", ({ params }) => {
    const existing = requisitions.find((item) => item.id === params.requisitionId);

    if (!existing) {
      return HttpResponse.json({ message: "Requisition not found", code: "not_found" }, { status: 404 });
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
      {
        id: `activity-${Date.now()}`,
        type: "requisition.submitted",
        message: "Submitted for review",
        actor: submitted.requester,
        occurredAt: submittedAt,
      },
    ];

    return HttpResponse.json({ data: submitted });
  }),

  http.get("/api/requisitions/:requisitionId/activity", ({ params }) => {
    return HttpResponse.json({ data: activity[String(params.requisitionId)] ?? [] });
  }),
];

function buildRequisition(
  values: RequisitionFormValues,
  id: string,
  status: Requisition["status"],
  number = "REQ-2026-000099",
): Requisition {
  const totals = calculateEstimatedTotal(values.lineItems);
  const now = new Date().toISOString();

  return {
    id,
    number,
    tenantId: "tenant-1",
    title: values.title,
    status,
    businessJustification: values.businessJustification,
    neededByDate: values.neededByDate,
    department: values.department,
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
    createdAt: now,
    updatedAt: now,
    submittedAt: status === "submitted" ? now : null,
    permissions: {
      canUpdate: status === "draft",
      canSubmit: status === "draft",
      canViewActivity: true,
    },
  };
}
