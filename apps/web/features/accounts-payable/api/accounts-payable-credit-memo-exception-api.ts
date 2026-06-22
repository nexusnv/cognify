import {
  listSupplierCreditMemoExceptions,
  acknowledgeSupplierCreditMemoException,
  resolveSupplierCreditMemoException,
  escalateSupplierCreditMemoException,
} from "@cognify/api-client/endpoints";
import type {
  SupplierCreditMemoException,
  AcknowledgeSupplierCreditMemoExceptionRequest,
  ResolveSupplierCreditMemoExceptionRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { withActiveTenantHeader, throwResponseData, unwrapData } from "./api-helpers";

export async function listCreditMemoExceptions(
  creditMemoId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemoException[]> {
  const response = await listSupplierCreditMemoExceptions(creditMemoId, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  const body = response.data as { data?: SupplierCreditMemoException[] };
  return body.data ?? [];
}

export async function acknowledgeCreditMemoException(
  creditMemoId: string,
  exceptionId: string,
  payload: AcknowledgeSupplierCreditMemoExceptionRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemoException> {
  const response = await acknowledgeSupplierCreditMemoException(creditMemoId, exceptionId, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemoException>(response);
}

export async function resolveCreditMemoException(
  creditMemoId: string,
  exceptionId: string,
  payload: ResolveSupplierCreditMemoExceptionRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemoException> {
  const response = await resolveSupplierCreditMemoException(creditMemoId, exceptionId, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemoException>(response);
}

export async function escalateCreditMemoException(
  creditMemoId: string,
  exceptionId: string,
  payload: AcknowledgeSupplierCreditMemoExceptionRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemoException> {
  const response = await escalateSupplierCreditMemoException(creditMemoId, exceptionId, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemoException>(response);
}
