# Learnings

## Migration Conventions (Laravel API)

- UUID PK: `$table->uuid('id')->primary();`
- Tenant FK: `$table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();`
- User FKs use `$table->foreignIdFor(User::class, 'column_name')->nullable()->constrained('users')->nullOnDelete();`
- Pivot table unique constraint: `$table->unique(['parent_id', 'child_id']);`
- Lock version: `$table->unsignedInteger('lock_version')->default(1);`
- Decimal amounts: `$table->decimal('column', 18, 4)->default(0);`
- String with explicit length: `$table->string('column', N)`
- Down always uses `Schema::dropIfExists('table_name');`
- `foreignUuid` for UUID FK columns: `$table->foreignUuid('col')->constrained('table')->cascadeOnDelete();

## Generated Type Mismatches (Task 18 - UI Components)

- `ApPaymentHandoff` generated type does NOT include `number` or `readinessWarnings` fields — need to use extended type in workspace component
- `ApPaymentHandoffInvoiceItem` only has `{ id, number, invoiceNumber, totalAmount, dueDate, currency }` — no vendor or PO number fields
- `SupplierInvoiceQueueItem` is used for eligible invoices in create dialog (has vendor name, currency, totalAmount, invoiceNumber, dueDate)
- `totalAmount` is `string` not `number` in both handoff and invoice types — need `parseFloat()` for arithmetic
