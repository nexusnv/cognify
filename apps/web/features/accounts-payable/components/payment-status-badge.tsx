import { Badge, Tooltip, TooltipContent, TooltipTrigger } from "@cognify/ui";
import type { SupplierInvoiceQueueItemPaymentStatus } from "@cognify/api-client/schemas";

interface PaymentStatusBadgeProps {
  paymentStatus: SupplierInvoiceQueueItemPaymentStatus | null | undefined;
  paymentStatusLabel?: string | null;
  paymentOnHoldReason?: string | null;
  activeHandoffNumber?: string | null | undefined;
  showLabel?: boolean;
}

const statusStyles: Record<
  NonNullable<SupplierInvoiceQueueItemPaymentStatus>,
  string
> = {
  payment_eligible: "bg-green-100 text-green-800 hover:bg-green-100",
  on_hold: "bg-amber-100 text-amber-800 hover:bg-amber-100",
  payment_ready: "bg-blue-100 text-blue-800 hover:bg-blue-100",
  handoff_exported: "bg-gray-100 text-gray-800 hover:bg-gray-100",
};

const defaultLabels: Record<
  NonNullable<SupplierInvoiceQueueItemPaymentStatus>,
  string
> = {
  payment_eligible: "Payment eligible",
  on_hold: "On hold",
  payment_ready: "Payment ready",
  handoff_exported: "Exported",
};

export function PaymentStatusBadge({
  paymentStatus,
  paymentStatusLabel,
  paymentOnHoldReason,
  activeHandoffNumber,
  showLabel,
}: PaymentStatusBadgeProps) {
  if (!paymentStatus) {
    return null;
  }

  const label = paymentStatusLabel ?? defaultLabels[paymentStatus];
  const className = statusStyles[paymentStatus];

  const badge = <Badge className={className}>{label}</Badge>;

  const content = (
    <div className="flex items-center gap-2">
      {paymentStatus === "on_hold" && paymentOnHoldReason ? (
        <Tooltip>
          <TooltipTrigger className="cursor-default">{badge}</TooltipTrigger>
          <TooltipContent>
            <p>{paymentOnHoldReason}</p>
          </TooltipContent>
        </Tooltip>
      ) : paymentStatus === "payment_ready" && activeHandoffNumber ? (
        <Tooltip>
          <TooltipTrigger className="cursor-default">{badge}</TooltipTrigger>
          <TooltipContent>
            <p>Handoff: {activeHandoffNumber}</p>
          </TooltipContent>
        </Tooltip>
      ) : (
        badge
      )}
      {showLabel && (
        <span className="text-sm text-muted-foreground">{label}</span>
      )}
    </div>
  );

  return content;
}
