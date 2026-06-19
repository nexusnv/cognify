# Task 15: Payment Fixtures & Handlers — Learnings

## Approach
- Created two new files + modified three existing files
- Followed exact MSW handler patterns from existing `accounts-payable-invoice-handlers.ts`
- Used locally-defined interfaces to avoid importing from `@cognify/api-client/schemas` (types don't exist yet)

## Key Observations
- `SupplierInvoiceQueueItem` and `SupplierInvoice` types from generated schemas don't include `paymentStatus`. Had to use intersection types `(SupplierInvoiceQueueItem & { paymentStatus: null })[]` to add the field
- MSW handler pattern: immutable fixture import → mutable state via `structuredClone` → reset function → handler array with 404/409/422 guards → lockVersion increment on mutation
- 12 MSW handlers created for payment endpoints (3 invoice-payment + 9 handoff)

## Gotchas
- Interface exports needed `export` keyword in fixtures file for import in handlers file
- The `now` variable in `refresh` handler was shadowing after removing the comment — need to be careful when removing comments adjacent to variable declarations
- TypeScript intersection types work well for extending generated types with mock-only fields
