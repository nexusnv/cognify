import { http, HttpResponse } from "msw";
import { approvalHandlers } from "@/features/approvals/mocks/approval-handlers";
import { accountsPayableInvoiceHandlers } from "@/features/accounts-payable/mocks/accounts-payable-invoice-handlers";
import { accountsPayablePaymentHandlers } from "@/features/accounts-payable/mocks/accounts-payable-payment-handlers";
import { accountsPayablePaymentStatusHandlers } from "@/features/accounts-payable/mocks/accounts-payable-payment-status-handlers";
import { accountsPayablePaymentAllocationHandlers } from "@/features/accounts-payable/mocks/accounts-payable-payment-allocation-handlers";
import { accountsPayablePaymentImportHandlers } from "@/features/accounts-payable/mocks/accounts-payable-payment-import-handlers";
import { invoiceExceptionHandlers } from "@/features/accounts-payable/mocks/invoice-exception-handlers";
import { attachmentHandlers } from "../../features/attachments/mocks/attachments-handlers";
import { auditHandlers } from "../../features/audit/mocks/audit-handlers";
import { identityHandlers } from "../../features/identity/mocks/identity-handlers";
import { notificationHandlers } from "../../features/notifications/mocks/notification-handlers";
import { systemReadinessHandlers } from "@/features/system-readiness/mocks/system-readiness-handlers";
import { searchHandlers } from "../../features/search/mocks/search-handlers";
import { requisitionsHandlers } from "../../features/requisitions/mocks/requisitions-handlers";
import { projectHandlers } from "@/features/projects/mocks/project-handlers";
import { purchaseOrderHandlers } from "@/features/purchase-orders/mocks/purchase-order-handlers";
import { purchaseOrderFulfillmentHandlers } from "@/features/purchase-orders/mocks/purchase-order-fulfillment-handlers";
import { purchaseOrderGoodsReceiptHandlers } from "@/features/purchase-orders/mocks/purchase-order-goods-receipt-handlers";
import { purchaseOrderSupplierInvoiceHandlers } from "@/features/purchase-orders/mocks/purchase-order-supplier-invoice-handlers";
import { vendorPortalHandlers } from "@/features/vendor-portal/mocks/vendor-portal-handlers";
import { sourcingHandlers } from "@/features/sourcing/mocks/sourcing-handlers";
import { rfqHandlers } from "@/features/sourcing/mocks/rfq-handlers";
import { vendorHandlers } from "@/features/sourcing/mocks/vendor-handlers";
import { rfqInvitationHandlers } from "@/features/sourcing/mocks/rfq-invitation-handlers";
import { quotationNormalizationHandlers } from "@/features/quotations/mocks/quotation-normalization-handlers";
import { procurementCalendarHandlers } from "@/features/procurement-calendar/mocks/procurement-calendar-handlers";
import { accountsPayableCreditMemoHandlers } from "@/features/accounts-payable/mocks/accounts-payable-credit-memo-handlers";
import { accountsPayableCreditApplicationHandlers } from "@/features/accounts-payable/mocks/accounts-payable-credit-application-handlers";
import { accountsPayableCreditMemoExceptionHandlers } from "@/features/accounts-payable/mocks/accounts-payable-credit-memo-exception-handlers";

export const handlers = [
  http.get("/api/health", () => {
    return HttpResponse.json({
      status: "ok",
      service: "cognify-api",
    });
  }),
  ...accountsPayableInvoiceHandlers,
  ...accountsPayablePaymentHandlers,
  ...accountsPayablePaymentStatusHandlers,
  ...accountsPayablePaymentAllocationHandlers,
  ...accountsPayablePaymentImportHandlers,
  ...accountsPayableCreditMemoHandlers,
  ...accountsPayableCreditApplicationHandlers,
  ...accountsPayableCreditMemoExceptionHandlers,
  ...invoiceExceptionHandlers,
  ...approvalHandlers,
  ...requisitionsHandlers,
  ...projectHandlers,
  ...purchaseOrderHandlers,
  ...purchaseOrderFulfillmentHandlers,
  ...purchaseOrderGoodsReceiptHandlers,
  ...purchaseOrderSupplierInvoiceHandlers,
  ...vendorPortalHandlers,
  ...sourcingHandlers,
  ...vendorHandlers,
  ...rfqHandlers,
  ...rfqInvitationHandlers,
  ...quotationNormalizationHandlers,
  ...procurementCalendarHandlers,
  ...searchHandlers,
  ...attachmentHandlers,
  ...identityHandlers,
  ...notificationHandlers,
  ...systemReadinessHandlers,
  ...auditHandlers,
];
