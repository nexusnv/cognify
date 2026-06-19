## Architectural Decisions

1. **Fixed OpenAPI spec drift first** — Missing payment fields in SupplierInvoiceQueueItem were blocking frontend types. Fixed spec and regenerated client before frontend work.

2. **Kept EvaluatePaymentReadiness intact** — Even though it's no longer called from approval actions, left it for backward compatibility.

3. **Payment tabs as separate Tabs section** — Added as a second `<Tabs>` group below review status filter buttons with visual separator, keeping payment filter independent from review status.

4. **Optional lockVersion in refresh** — RefreshApPaymentHandoffSnapshotRequest has lockVersion as optional; markReady/cancel require it.

5. **Regular functions over useCallback** — For callbacks that depend on handoff data (only available after early returns), use regular function declarations instead of useCallback to avoid React hooks ordering issues.

6. **MSW mocking with in-memory store** — Handlers use an in-memory store with reset function, registered in global test setup.
