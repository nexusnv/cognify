# Draft: P1-49 Credit Memo and Invoice Adjustment

## Requirements (confirmed)
- Source: `docs/01-product/feature-roadmap.md` P1-49 row
- Roadmap text: "Support credits, debit notes, invoice reversals, and invoice adjustments linked to original invoices and purchase order lines. Real AP operations need controlled correction paths."
- Predecessor P1-48 was delivered with ~10x scope expansion (Payment Status Tracking → full Payments domain with allocations, imports, void, fail, variance, reschedule)
- P1-48 explicitly left hooks for P1-49: "Credit memo, debit note, invoice reversal, post-paid adjustment handling (P1-49 scope)" and "`reversed` state for post-paid corrections (P1-49 scope)"
- User wants to analyze whether P1-49 needs scope expansion like P1-48 did
- User wants research-backed options with recommendations before deciding
- User instruction: when handing off to design spec agent, tell them NOT to commit — only make edits, user will review manually

## Technical Decisions (CONFIRMED by user)
- Modeling approach: NEW SupplierCreditMemo model + NEW CreditMemo domain (apps/api/Domains/CreditMemo/)
- Document types in scope: Vendor credit memos + invoice adjustments (post-approval line corrections via linked credit). DEFER debit notes, full reversals.
- Application model: Post-first-then-apply (credit memo posted → creates credit on vendor account → then applied to invoice(s) via CreditApplication junction)
- Payment interaction: Netting (reduce outstanding balance on partially_paid/payment_eligible invoices) + introduce `reversed` payment status for fully offset invoices. DEFER refund/cash recovery path.
- Approval routing: Shared Approval domain with credit-specific SupplierCreditMemoApprovalSubjectHandler
- Reversed payment status: YES — mandated by P1-48 non-goals
- Scope expansion level: Disciplined expansion following P1-48 pattern (~5-6x from roadmap description)
- Tax: Mirror original invoice tax codes. DEFER multi-currency gain/loss.
- Numbering: CM-2026-000001 (mirrors INV-2026-000001 pattern)
- Validation: Lightweight (vendor match, amount ≤ invoice, tax mirror) + exception queue. NO 3-way match.
- Handoff instruction: Design spec agent must NOT commit — only make edits. User reviews manually.

## Research Findings

### P1-48 Expansion Pattern (from bg_68d43dd9)
- Roadmap asked for ~2 capabilities; spec delivered ~20 (10x expansion)
- Pattern: research mature ERPs → identify operational deadlocks → build minimal schema to prevent deadlocks → draw clean domain boundaries → dual-level tracking → defer everything that can wait → leave forward-looking hooks
- P1-48 non-goals explicitly deferred to P1-49:
  - "Credit memo, debit note, invoice reversal, or post-paid adjustment handling (P1-49 scope)"
  - "`reversed` state for post-paid corrections (P1-49 scope)"
- P1-48 completion definition: "Downstream P1-49 has a clear `paid`, `partially_paid`, and `voided` precondition to consume"
- P1-48 left structural hooks: ApPaymentAllocation.voided_at, variance_amount/variance_reason, partially_paid outstanding balance

### Cognify Invoice/AP Domain Current State (from bg_a14dd737)
- Domains: Invoice, AccountsPayable, Payments, Approval
- Invoice states: captured → in_review → reviewed → matched/mismatch → ready_for_approval → in_approval → approved (terminal)
- Payment states: payment_eligible → payment_ready → handoff_exported → payment_scheduled → paid (terminal); branches: on_hold, partially_paid, failed, voided
- Handoff states: draft → ready → exported → scheduled → paid/failed/voided; cancelled
- NO existing credit/debit/reversal concepts in the codebase
- Only "adjustment" = pre-approval value_adjustment via exception resolution (overwrites line values)
- Duplicate detection: (tenant_id, purchase_order_id, invoice_number_normalized)
- All invoice/payment permissions: buyer or admin only
- ApPaymentAllocation has voided_at — extendable for credit linkage

### Credit Memo Best Practices (from bg_ba9de21a + websearch)
- Consensus state machine: Draft → Pending Approval → Approved → Open/Unapplied → Partially Applied → Fully Applied → Closed; Voided
- Four document types: vendor credit memo (vendor-issued), debit note (buyer-issued), invoice correction, full reversal
- Linking: header-level, line-level, or PO-level (Oracle/NetSuite/SAP all support multi-level)
- Three settlement patterns: netting (apply to future invoices), refund (cash recovery), retroactive (apply to paid invoice)
- NO full 3-way match for credit memos — but validation needed (vendor match, amount ≤ invoice, tax mirror)
- Separate approval workflow from invoices but can share infrastructure (NetSuite, SAP, Oracle)
- Separate numbering sequence (CM- prefix, system-generated, sequential)
- Tax: mirror original invoice tax codes (NetSuite, Oracle, SAP)
- Edge cases: partial application, expired credits, over-credits, cross-PO, voided invoices
- Oracle Payables: credit memos match to invoices, invoice distributions, or PO shipments; supports price corrections
- SAP S/4HANA: four transaction types (Invoice, Credit Memo, Subsequent Debit, Subsequent Credit)
- NetSuite: vendor credits apply to bills, partial application with remaining tracking, RTV flow with evidence

## Scope Boundaries (CONFIRMED)
- INCLUDE: vendor credit memos (full lifecycle), invoice adjustments (post-approval line corrections via linked credit), credit application to invoices (full and partial), netting against outstanding balance, reversed payment status, shared approval routing, lightweight validation + exception queue, separate numbering (CM- prefix), tax mirroring from original invoice, PO line linkage (for P1-50 budget relief), audit events, web UI (queue + detail + application panel), seed data, MSW mocks
- EXCLUDE: debit notes (buyer-issued), full invoice reversals (complete nullification), refund/cash recovery path, multi-currency realized gain/loss, vendor portal credit memo submission, OCR/email intake automation, credit memo expiration/write-off workflow, cross-subsidiary application

## Open Questions for User
1. Modeling approach: new SupplierCreditMemo model vs type field on SupplierInvoice?
2. Document types: credits only, or also debit notes and full reversals?
3. Application model: post-first-then-apply vs direct reduction?
4. Payment interaction: netting only, or also refund path?
5. How much to expand scope given we're nearing end of P1?
