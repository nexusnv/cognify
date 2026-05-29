# P1 Medium Audit Remediation Notes

- Date: 2026-05-28
- Scope: M6-M9 from `docs/superpowers/plans/2026-05-28-p1-medium-audit-remediation.md`

## M6 Award approval and PO handoff

- Finding: the approval and handoff docs still implied that award approval must not create PO handoff side effects.
- Decision: final award approval intentionally auto-creates or reveals a draft PO handoff.
- Behavior after remediation: approval remains the decision boundary; PO handoff remains the operational handoff boundary; handoff creation is idempotent and only happens after approval succeeds.
- Proof: `apps/api/tests/Feature/PurchaseOrderRequestHandoffApiTest.php` covers auto-creation and idempotency.

## M7 Vendor portal audit counting

- Finding: portal view counting happened on every token resolution.
- Decision: only the package-open route records a vendor portal view.
- Behavior after remediation: `GET /api/vendor-portal/rfq-invitations/{token}` increments `portal_view_count` and records `rfq_invitation.portal_viewed`; quotation, version, upload, and manual-entry routes remain read-only with respect to view counting.

## M8 Quotation upload clarification

- Finding: the upload docs implied multi-file batch upload when the implementation only supports repeated single-file uploads.
- Decision: keep the current behavior and describe it precisely.
- Behavior after remediation: buyers and vendors upload one file per request; multiple evidence files are added by repeating the upload action.

## M9 Audit subject filtering

- Finding: the audit feed only accepted requisition subjects in the filter.
- Decision: expose the public P1 audit subject types through a centralized whitelist.
- Behavior after remediation: `subjectType` accepts requisition, attachment, project, RFQ, invitation, quotation, quotation version, quotation normalization, scorecard, award, approval task, and PO handoff subjects.
