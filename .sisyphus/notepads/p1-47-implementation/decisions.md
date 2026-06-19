# P1-47 Implementation Decisions

## Architecture Decisions (from spec)

1. New `Domains/AccountsPayable` domain for post-approval payment lifecycle
2. `payment_status` as a separate column on `SupplierInvoice` (orthogonal to main `status`)
3. `ApPaymentHandoff` snapshot model following `PurchaseOrderRequestHandoff` pattern
4. Auto-advance fires in same transaction as approval; failure leaves `payment_status = null`
5. Currency homogeneity enforced at handoff creation (422 on mismatch)
6. Snapshot is dynamic in draft, locks at ready transition
7. Lock-version concurrency on all mutations
