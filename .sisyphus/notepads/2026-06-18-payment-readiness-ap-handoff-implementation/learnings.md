## Learnings & Conventions

### Backend
- AutoAdvanceToPaymentEligible uses try/catch in approval actions so approval outcome is never rolled back by auto-advance failure
- Handoff actions use lockVersion for optimistic concurrency control (refresh optional, markReady/cancel required)
- Currency homogeneity enforced at handoff creation (distinct currencies > 1 → 422)
- MarkReady recalculates snapshot one final time then locks it
- All actions follow: lockForUpdate → status guard → assertLockVersion → capture before → forceFill → audit

### Frontend
- Payment queue is a route page that reuses the existing invoice queue page component
- Payment tabs are a second `<Tabs>` section below matching filter buttons with visual separator
- All hooks must be declared before early returns; use regular functions (not useCallback) after data validation
- Mutation callbacks needing `handoff.lockVersion` should be defined as regular functions after the early-return guards

### OpenAPI
- SupplierInvoiceQueueItem needed paymentStatus, paymentStatusLabel, paymentOnHoldReason, paymentEligibleAt, paymentOnHoldAt, paymentOnHoldByUserId, activeHandoffId, activeHandoffNumber fields added
- These are all optional (nullable) fields to maintain backward compatibility
