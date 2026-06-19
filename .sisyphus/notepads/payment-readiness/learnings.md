# Payment Readiness - Component Implementation

## Patterns Observed
- Badge components use inline Tailwind color classes (e.g., `bg-green-100 text-green-800 hover:bg-green-100`) rather than `variant` props for custom colors
- Badges use `Badge` from `@cognify/ui` with `className` for color, matching `InvoiceMatchingStatusBadge` style
- Tooltip usage: `Tooltip > TooltipTrigger > Badge` wrapping works without `asChild` on TooltipTrigger
- Panel components use Card structure: `Card > CardHeader (CardTitle + CardDescription) > CardContent`
- Mutation panels follow pattern: inline `errorToMessage()` helper, `mutate()` with `onSettled`/`onError`, `isPending` for loading state
- Dialog pattern: `Dialog > DialogTrigger(asChild) > Button`, `DialogContent > DialogHeader(DialogTitle+DialogDescription) + form fields + DialogFooter(Cancel+Submit buttons)`

## API Types
- `SupplierInvoiceQueueItemPaymentStatus` = `"payment_eligible" | "on_hold" | "payment_ready" | "handoff_exported" | null`
- `PlaceInvoiceOnHoldRequest` = `{ reason: string; lockVersion: number }`
- `ReleaseInvoiceHoldRequest` = `{ releaseNote: string; lockVersion: number }`
- `SupplierInvoicePaymentResponseData` = `{ id: string; paymentStatus; paymentStatusLabel; lockVersion }`
- `RetryPaymentInductionRequest` = `{ lockVersion: number }`

## Task 19 — Wire Payment Tabs into Invoice Queue Page

### Files Modified
- `apps/web/features/accounts-payable/api/accounts-payable-invoices-api.ts` — extended `AccountsPayableInvoiceFilters` with `paymentStatus?: string` to support payment status filtering
- `apps/web/features/accounts-payable/workflows/accounts-payable-invoice-queue-page.tsx` — added payment status tabs (All / Payment eligible / On hold / Awaiting induction) below existing status tabs, integrated `PaymentHoldPanel` for eligible/on-hold invoices in side panel, added `RetryInductionPanel` component for invoices with no payment status
- `apps/web/features/accounts-payable/tables/accounts-payable-invoice-queue-table.tsx` — added `paymentStatus` column after `matchingStatus` using `PaymentStatusBadge`, added contextual action buttons (Hold payment / Release hold / Retry induction) in row actions that trigger side panel via `onSelect`

### Payment Filter Values
- `ListSupplierInvoiceQueueParams` generated type lacks `paymentStatus` — extended locally via intersection type on `AccountsPayableInvoiceFilters`
- Payment filter values: `"payment_eligible"`, `"on_hold"`, `"none"` (represents null/awaiting induction), `"_all"` (no filter, the default)
- Payment filter uses a separate `paymentStatus` state independent from the main `status` state, so both filters compose together

### Side Panel Integration
- `PaymentHoldPanel` conditionally rendered when `selectedInvoice?.paymentStatus` is truthy — handles place/release hold forms
- `RetryInductionPanel` custom component rendered when `selectedInvoice && !selectedInvoice.paymentStatus` — calls `useRetryInvoicePaymentInduction` with lock version
- Both panels share the standard `Card` layout pattern and call `invoicesQuery.refetch()` on mutation settle
