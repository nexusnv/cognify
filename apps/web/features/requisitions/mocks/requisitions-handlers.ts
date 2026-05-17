import { http, HttpResponse } from "msw";
import type { AuditEvent } from "@cognify/api-client/schemas";
import { calculateEstimatedTotal } from "../utils/requisition-totals";
import {
  requisitionActivityFixtures,
  requisitionCommentFixtures,
  requisitionFixtures,
  requisitionIntakeOptionsFixture,
  requisitionItemSuggestionFixtures,
  requisitionMentionCandidateFixtures,
  requisitionTemplateFixtures,
} from "./requisitions-fixtures";
import type {
  CollaborationComment,
  Requisition,
  RequisitionFormValues,
  RequisitionStatus,
  RequisitionTemplate,
  UserSummary,
} from "../types/requisition-view-model";

let requisitions = [...requisitionFixtures];
let activity = structuredClone(requisitionActivityFixtures);
let comments = structuredClone(requisitionCommentFixtures);
let requisitionSequence = requisitionFixtures.length;
let activitySequence = 0;
let commentSequence = Object.values(requisitionCommentFixtures).reduce(
  (total, requisitionComments) => total + requisitionComments.length,
  0,
);

export function resetRequisitionMockState() {
  requisitions = [...requisitionFixtures];
  activity = structuredClone(requisitionActivityFixtures);
  comments = structuredClone(requisitionCommentFixtures);
  requisitionSequence = requisitionFixtures.length;
  activitySequence = 0;
  commentSequence = Object.values(requisitionCommentFixtures).reduce(
    (total, requisitionComments) => total + requisitionComments.length,
    0,
  );
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
    const department = url.searchParams.get("department")?.toLowerCase();
    const queuePreset = url.searchParams.get("queuePreset");
    const amountMin = toOptionalNumber(url.searchParams.get("amountMin"));
    const amountMax = toOptionalNumber(url.searchParams.get("amountMax"));
    const updatedFrom = url.searchParams.get("updatedFrom");
    const updatedTo = url.searchParams.get("updatedTo");

    const data = requisitions.filter((requisition) => {
      const matchesSearch =
        !search ||
        requisition.title.toLowerCase().includes(search) ||
        requisition.number.toLowerCase().includes(search);
      const matchesStatus = !status || requisition.status === status;
      const matchesDepartment =
        !department || requisition.department?.toLowerCase().includes(department);
      const matchesAmountMin = amountMin === undefined || requisition.estimatedTotal >= amountMin;
      const matchesAmountMax = amountMax === undefined || requisition.estimatedTotal <= amountMax;
      const matchesUpdatedFrom = !updatedFrom || requisition.updatedAt >= updatedFrom;
      const matchesUpdatedTo = !updatedTo || requisition.updatedAt <= updatedTo;
      const matchesQueuePreset = matchesQueueFilter(requisition, queuePreset);

      return (
        matchesSearch &&
        matchesStatus &&
        matchesDepartment &&
        matchesAmountMin &&
        matchesAmountMax &&
        matchesUpdatedFrom &&
        matchesUpdatedTo &&
        matchesQueuePreset
      );
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

    if (existing.status !== "draft" && existing.status !== "changes_requested") {
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
      existing.status,
      existing.number,
      existing.createdAt,
      existing.lockVersion + 1,
      {
        submittedAt: existing.submittedAt ?? null,
        changesRequestedAt: existing.changesRequestedAt ?? null,
        changesRequestedBy: existing.changesRequestedBy ?? null,
        changeRequestReason: existing.changeRequestReason ?? null,
        changeRequestFields: existing.changeRequestFields,
        withdrawnAt: existing.withdrawnAt ?? null,
        withdrawnBy: existing.withdrawnBy ?? null,
        withdrawalReason: existing.withdrawalReason ?? null,
        cancelledAt: existing.cancelledAt ?? null,
        cancelledBy: existing.cancelledBy ?? null,
        cancellationReason: existing.cancellationReason ?? null,
      },
    );
    updateRequisitionState(updated);
    pushActivity(
      updated.id,
      buildActivityEvent(
        "requisition.updated",
        "Draft updated",
        updated,
        updated.updatedAt,
        existing,
        updated,
      ),
    );

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

    if (existing.status !== "draft" && existing.status !== "changes_requested") {
      return HttpResponse.json(
        { message: "Only editable requisitions can receive templates.", code: "invalid_state" },
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
      existing.status,
      existing.number,
      existing.createdAt,
      existing.lockVersion + 1,
      {
        submittedAt: existing.submittedAt ?? null,
        changesRequestedAt: existing.changesRequestedAt ?? null,
        changesRequestedBy: existing.changesRequestedBy ?? null,
        changeRequestReason: existing.changeRequestReason ?? null,
        changeRequestFields: existing.changeRequestFields,
        withdrawnAt: existing.withdrawnAt ?? null,
        withdrawnBy: existing.withdrawnBy ?? null,
        withdrawalReason: existing.withdrawalReason ?? null,
        cancelledAt: existing.cancelledAt ?? null,
        cancelledBy: existing.cancelledBy ?? null,
        cancellationReason: existing.cancellationReason ?? null,
      },
    );

    updateRequisitionState(updated);
    pushActivity(
      updated.id,
      buildActivityEvent(
        "requisition.template_applied",
        "Template applied",
        updated,
        updated.updatedAt,
        existing,
        updated,
      ),
    );

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
        canResubmit: false,
        canRequestChanges: true,
        canWithdraw: false,
      },
    };
    updateRequisitionState(submitted);
    pushActivity(
      submitted.id,
      buildActivityEvent(
        "requisition.submitted",
        "Submitted for review",
        submitted,
        submittedAt,
        existing,
        submitted,
      ),
    );

    return HttpResponse.json({ data: submitted });
  }),

  http.post("/api/requisitions/:requisitionId/request-changes", async ({ params, request }) => {
    const requisitionId = String(params.requisitionId);
    const existing = requisitions.find((item) => item.id === requisitionId);
    const body = (await request.json()) as { reason?: string; requestedFields?: string[] };

    if (!existing) {
      return errorResponse(404, "not_found", "Requisition not found.");
    }

    if (existing.status !== "submitted") {
      return errorResponse(
        409,
        "conflict",
        "Only submitted requisitions can receive change requests.",
      );
    }

    const updatedAt = new Date().toISOString();
    const updated: Requisition = {
      ...existing,
      status: "changes_requested",
      updatedAt,
      changesRequestedAt: updatedAt,
      changesRequestedBy: requisitionMentionCandidateFixtures[1] ?? null,
      changeRequestReason: body.reason?.trim() ?? "",
      changeRequestFields: (body.requestedFields ?? []).filter(Boolean),
      permissions: {
        ...existing.permissions,
        canUpdate: true,
        canSubmit: false,
        canResubmit: true,
        canRequestChanges: false,
        canWithdraw: true,
      },
    };

    updateRequisitionState(updated);
    pushActivity(
      requisitionId,
      buildActivityEvent(
        "requisition.changes_requested",
        "Changes requested",
        updated,
        updatedAt,
        existing,
        updated,
        {
          requestedFields: updated.changeRequestFields,
        },
      ),
    );

    return HttpResponse.json({ data: updated });
  }),

  http.post("/api/requisitions/:requisitionId/resubmit", ({ params }) => {
    const requisitionId = String(params.requisitionId);
    const existing = requisitions.find((item) => item.id === requisitionId);

    if (!existing) {
      return errorResponse(404, "not_found", "Requisition not found.");
    }

    if (existing.status !== "changes_requested") {
      return errorResponse(409, "conflict", "Only change-requested requisitions can be resubmitted.");
    }

    const updatedAt = new Date().toISOString();
    const updated: Requisition = {
      ...existing,
      status: "submitted",
      updatedAt,
      submittedAt: updatedAt,
      changesRequestedAt: null,
      changesRequestedBy: null,
      changeRequestReason: null,
      changeRequestFields: [],
      permissions: {
        ...existing.permissions,
        canUpdate: false,
        canSubmit: false,
        canResubmit: false,
        canRequestChanges: true,
        canWithdraw: false,
      },
    };

    updateRequisitionState(updated);
    pushActivity(
      requisitionId,
      buildActivityEvent(
        "requisition.resubmitted",
        "Requisition resubmitted",
        updated,
        updatedAt,
        existing,
        updated,
      ),
    );

    return HttpResponse.json({ data: updated });
  }),

  http.post("/api/requisitions/:requisitionId/withdraw", async ({ params, request }) => {
    const requisitionId = String(params.requisitionId);
    const existing = requisitions.find((item) => item.id === requisitionId);
    const body = (await request.json()) as { reason?: string };

    if (!existing) {
      return errorResponse(404, "not_found", "Requisition not found.");
    }

    if (existing.status === "withdrawn" || existing.status === "cancelled") {
      return errorResponse(409, "conflict", "Stopped requisitions cannot be changed.");
    }

    const updatedAt = new Date().toISOString();
    const updated: Requisition = {
      ...existing,
      status: "withdrawn",
      updatedAt,
      withdrawnAt: updatedAt,
      withdrawnBy: requisitionMentionCandidateFixtures[0] ?? null,
      withdrawalReason: body.reason?.trim() ?? "",
      permissions: {
        ...existing.permissions,
        canUpdate: false,
        canSubmit: false,
        canResubmit: false,
        canRequestChanges: false,
        canWithdraw: false,
        canCancel: false,
        canComment: false,
        canMention: false,
      },
    };

    updateRequisitionState(updated);
    pushActivity(
      requisitionId,
      buildActivityEvent(
        "requisition.withdrawn",
        "Requisition withdrawn",
        updated,
        updatedAt,
        existing,
        updated,
        {
          reason: updated.withdrawalReason,
        },
      ),
    );

    return HttpResponse.json({ data: updated });
  }),

  http.post("/api/requisitions/:requisitionId/cancel", async ({ params, request }) => {
    const requisitionId = String(params.requisitionId);
    const existing = requisitions.find((item) => item.id === requisitionId);
    const body = (await request.json()) as { reason?: string };

    if (!existing) {
      return errorResponse(404, "not_found", "Requisition not found.");
    }

    if (existing.status !== "submitted" && existing.status !== "changes_requested") {
      return errorResponse(
        409,
        "conflict",
        "Only submitted or change-requested requisitions can be cancelled.",
      );
    }

    const updatedAt = new Date().toISOString();
    const updated: Requisition = {
      ...existing,
      status: "cancelled",
      updatedAt,
      cancelledAt: updatedAt,
      cancelledBy: requisitionMentionCandidateFixtures[2] ?? null,
      cancellationReason: body.reason?.trim() ?? "",
      permissions: {
        ...existing.permissions,
        canUpdate: false,
        canSubmit: false,
        canResubmit: false,
        canRequestChanges: false,
        canWithdraw: false,
        canCancel: false,
        canComment: false,
        canMention: false,
      },
    };

    updateRequisitionState(updated);
    pushActivity(
      requisitionId,
      buildActivityEvent(
        "requisition.cancelled",
        "Requisition cancelled",
        updated,
        updatedAt,
        existing,
        updated,
        {
          reason: updated.cancellationReason,
        },
      ),
    );

    return HttpResponse.json({ data: updated });
  }),

  http.get("/api/requisitions/:requisitionId/activity", ({ params }) => {
    return HttpResponse.json({ data: activity[String(params.requisitionId)] ?? [] });
  }),

  http.get("/api/requisitions/:requisitionId/comments", ({ params }) => {
    return HttpResponse.json({ data: comments[String(params.requisitionId)] ?? [] });
  }),

  http.get("/api/requisitions/:requisitionId/mention-candidates", ({ params }) => {
    const existing = requisitions.find((item) => item.id === String(params.requisitionId));

    if (!existing) {
      return errorResponse(404, "not_found", "Requisition not found.");
    }

    return HttpResponse.json({
      data: requisitionMentionCandidateFixtures,
    });
  }),

  http.post("/api/requisitions/:requisitionId/comments", async ({ params, request }) => {
    const requisitionId = String(params.requisitionId);
    const existing = requisitions.find((item) => item.id === requisitionId);
    const body = (await request.json()) as { body?: string; mentionedUserIds?: string[] };

    if (!existing) {
      return errorResponse(404, "not_found", "Requisition not found.");
    }

    if (!body.body?.trim()) {
      return HttpResponse.json(
        {
          error: {
            code: "validation_failed",
            message: "Validation failed",
            details: {
              fields: { body: ["Comment is required."] },
            },
          },
        },
        { status: 422 },
      );
    }

    const mentionedUsers = (body.mentionedUserIds ?? [])
      .map((mentionedUserId) =>
        requisitionMentionCandidateFixtures.find((candidate) => candidate.id === mentionedUserId),
      )
      .filter((candidate): candidate is UserSummary => Boolean(candidate))
      .map((candidate) => ({
        id: `mention-${candidate.id}`,
        mentionedUser: candidate,
      }));

    const timestamp = new Date().toISOString();
    const comment: CollaborationComment = {
      id: `comment-${++commentSequence}`,
      subjectType: "requisition",
      subjectId: requisitionId,
      author: requisitionMentionCandidateFixtures[0] ?? existing.requester,
      body: body.body.trim(),
      mentions: mentionedUsers,
      createdAt: timestamp,
      updatedAt: timestamp,
    };

    comments[requisitionId] = [...(comments[requisitionId] ?? []), comment];
    pushActivity(
      requisitionId,
      buildActivityEvent(
        "collaboration.comment_created",
        "Comment added",
        existing,
        timestamp,
      ),
    );
    if (mentionedUsers.length > 0) {
      pushActivity(
        requisitionId,
        buildActivityEvent(
          "collaboration.mentioned",
          "Collaborator mentioned",
          existing,
          timestamp,
          null,
          null,
          {
            users: mentionedUsers.map((mention) => mention.mentionedUser.name),
          },
        ),
      );
    }

    return HttpResponse.json({ data: comment }, { status: 201 });
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
  status: RequisitionStatus,
  number: string,
  createdAt?: string,
  lockVersion = 0,
  metadata?: Partial<
    Pick<
      Requisition,
      | "submittedAt"
      | "changesRequestedAt"
      | "changesRequestedBy"
      | "changeRequestReason"
      | "changeRequestFields"
      | "withdrawnAt"
      | "withdrawnBy"
      | "withdrawalReason"
      | "cancelledAt"
      | "cancelledBy"
      | "cancellationReason"
    >
  >,
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
    projectSummary: values.projectId === "501"
      ? {
          id: "501",
          number: "PRJ-2026-000501",
          name: "Office refresh",
          status: "active",
          owner: {
            id: "12",
            name: "Priya Buyer",
            email: "priya@example.test",
          },
        }
      : null,
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
    submittedAt: metadata?.submittedAt ?? (status === "submitted" ? now : null),
    changesRequestedAt: metadata?.changesRequestedAt ?? null,
    changesRequestedBy: metadata?.changesRequestedBy ?? null,
    changeRequestReason: metadata?.changeRequestReason ?? null,
    changeRequestFields: metadata?.changeRequestFields ?? [],
    withdrawnAt: metadata?.withdrawnAt ?? null,
    withdrawnBy: metadata?.withdrawnBy ?? null,
    withdrawalReason: metadata?.withdrawalReason ?? null,
    cancelledAt: metadata?.cancelledAt ?? null,
    cancelledBy: metadata?.cancelledBy ?? null,
    cancellationReason: metadata?.cancellationReason ?? null,
    permissions: buildPermissions(status),
  };
}

function buildPermissions(status: RequisitionStatus): Requisition["permissions"] {
  return {
    canUpdate: status === "draft" || status === "changes_requested",
    canSubmit: status === "draft",
    canResubmit: status === "changes_requested",
    canRequestChanges: status === "submitted",
    canWithdraw: status === "draft" || status === "changes_requested",
    canCancel: status === "submitted" || status === "changes_requested",
    canComment: status !== "withdrawn" && status !== "cancelled",
    canMention: status !== "withdrawn" && status !== "cancelled",
    canViewActivity: true,
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
  metadata: Record<string, unknown> = {},
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
      ...metadata,
    },
    before,
    after,
    occurredAt,
    requestId: null,
  };
}

function matchesQueueFilter(requisition: Requisition, queuePreset: string | null) {
  switch (queuePreset) {
    case null:
    case "":
    case "all_visible":
      return true;
    case "my_drafts":
      return requisition.status === "draft";
    case "submitted":
      return requisition.status === "submitted";
    case "needs_my_correction":
      return requisition.status === "changes_requested";
    case "buyer_review":
      return requisition.status === "submitted" || requisition.status === "changes_requested";
    case "stopped":
      return requisition.status === "withdrawn" || requisition.status === "cancelled";
    default:
      return true;
  }
}

function updateRequisitionState(updated: Requisition) {
  requisitions = requisitions.map((item) => (item.id === updated.id ? updated : item));
}

function pushActivity(requisitionId: string, event: AuditEvent) {
  activity[requisitionId] = [...(activity[requisitionId] ?? []), event];
}

function errorResponse(status: number, code: string, message: string) {
  return HttpResponse.json({ error: { code, message } }, { status });
}

function toOptionalNumber(value: string | null) {
  if (!value) return undefined;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : undefined;
}
