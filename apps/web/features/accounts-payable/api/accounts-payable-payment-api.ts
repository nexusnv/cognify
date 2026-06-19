"use client";

import {
  placeSupplierInvoiceOnPaymentHold,
  releaseSupplierInvoicePaymentHold,
  retrySupplierInvoicePaymentInduction,
} from "@cognify/api-client/endpoints";
import type {
  PlaceInvoiceOnHoldRequest,
  ReleaseInvoiceHoldRequest,
  RetryPaymentInductionRequest,
  SupplierInvoicePaymentResponseData,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { withActiveTenantHeader, unwrapData, throwResponseData } from "./api-helpers";

export async function placeInvoiceOnPaymentHold(
  invoiceId: string,
  payload: PlaceInvoiceOnHoldRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoicePaymentResponseData> {
  const response = await placeSupplierInvoiceOnPaymentHold(
    invoiceId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapData<SupplierInvoicePaymentResponseData>(response);
}

export async function releaseInvoicePaymentHold(
  invoiceId: string,
  payload: ReleaseInvoiceHoldRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoicePaymentResponseData> {
  const response = await releaseSupplierInvoicePaymentHold(
    invoiceId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapData<SupplierInvoicePaymentResponseData>(response);
}

export async function retryInvoicePaymentInduction(
  invoiceId: string,
  payload: RetryPaymentInductionRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierInvoicePaymentResponseData> {
  const response = await retrySupplierInvoicePaymentInduction(
    invoiceId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapData<SupplierInvoicePaymentResponseData>(response);
}
