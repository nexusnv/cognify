"use client";

import {
  completeSupplierInvoiceReview,
  listSupplierInvoiceMatchResults,
  listSupplierInvoiceQueue,
  markSupplierInvoiceNeedsInformation,
  runSupplierInvoiceMatching,
  showSupplierInvoice,
  startSupplierInvoiceReview,
} from "@cognify/api-client/endpoints";
import type {
  ListSupplierInvoiceQueueParams,
  SupplierInvoice,
  SupplierInvoiceCompleteReviewRequest,
  SupplierInvoiceNeedsInformationRequest,
  SupplierInvoiceQueueItem,
  SupplierInvoiceStartReviewRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export type AccountsPayableInvoiceFilters = ListSupplierInvoiceQueueParams;

export interface MatchResult {
  id: string;
  lineNumber: number | null;
  matchLevel: "header" | "line";
  matchType: "two_way" | "three_way";
  dimension: string;
  expectedValue: string | null;
  actualValue: string | null;
  tolerancePercentApplied: number | null;
  toleranceFloorApplied: number | null;
  toleranceCapApplied: number | null;
  result: "pass" | "fail" | "not_applicable";
  notes: string | null;
}

export interface MatchSummary {
  totalLines: number;
  matchedLines: number;
  mismatchLines: number;
  dimensionsWithIssues: string[];
}

function withActiveTenantHeader(tenantId: string | null = getStoredActiveTenantId()): RequestInit {
  if (!tenantId) {
    throw new Error("Missing active tenant context");
  }

  return {
    headers: {
      "X-Tenant-Id": tenantId,
    },
  };
}

function unwrapData<T>(response: { status: number; data?: unknown }, success = 200): T {
  if (response.status !== success) {
    throw response.data ?? response;
  }

  return (response.data as { data: T }).data;
}

function throwResponseData(error: unknown): never {
  if (typeof error === "object" && error !== null && "data" in error) {
    throw (error as { data: unknown }).data;
  }

  throw error;
}

export async function fetchAccountsPayableInvoices(
  filters: AccountsPayableInvoiceFilters,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoiceQueueItem[]> {
  const response = await listSupplierInvoiceQueue(filters, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierInvoiceQueueItem[]>(response);
}

export async function fetchSupplierInvoiceDetail(
  id: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoice> {
  const response = await showSupplierInvoice(id, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierInvoice>(response);
}

export async function startReview(
  id: string,
  payload: SupplierInvoiceStartReviewRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoice> {
  const response = await startSupplierInvoiceReview(id, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );
  return unwrapData<SupplierInvoice>(response);
}

export async function markNeedsInformation(
  id: string,
  payload: SupplierInvoiceNeedsInformationRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoice> {
  const response = await markSupplierInvoiceNeedsInformation(id, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );
  return unwrapData<SupplierInvoice>(response);
}

export async function completeReview(
  id: string,
  payload: SupplierInvoiceCompleteReviewRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoice> {
  const response = await completeSupplierInvoiceReview(id, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );
  return unwrapData<SupplierInvoice>(response);
}

export async function triggerInvoiceMatching(
  invoiceId: string,
  lockVersion: number,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoice> {
  const response = await runSupplierInvoiceMatching(
    invoiceId,
    { lockVersion },
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);
  return unwrapData<SupplierInvoice>(response);
}

export async function fetchInvoiceMatchResults(
  invoiceId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<MatchResult[]> {
  const response = await listSupplierInvoiceMatchResults(
    invoiceId,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);
  return unwrapData<MatchResult[]>(response);
}

export function buildMatchSummary(results: MatchResult[]): MatchSummary {
  const lineResults = results.filter((r) => r.matchLevel === "line");
  const lineNumbers = [...new Set(lineResults.map((r) => r.lineNumber).filter((n): n is number => n !== null))];
  const mismatchLines = [...new Set(lineResults.filter((r) => r.result === "fail").map((r) => r.lineNumber).filter((n): n is number => n !== null))];
  const dimensionsWithIssues = [...new Set(results.filter((r) => r.result === "fail").map((r) => r.dimension))];

  return {
    totalLines: lineNumbers.length,
    matchedLines: lineNumbers.length - mismatchLines.length,
    mismatchLines: mismatchLines.length,
    dimensionsWithIssues,
  };
}
