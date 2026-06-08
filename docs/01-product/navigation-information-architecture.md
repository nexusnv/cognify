# Navigation Information Architecture

## Purpose

This document records the target navigation model for Cognify's mature product experience. It is derived from the full capability roadmap in `docs/01-product/feature-roadmap.md`, but it intentionally does not expose the roadmap as a sidebar tree.

Cognify should feel like a dense operational product that remains calm to use. The primary navigation should help users move between major work areas. Search, favorites, saved views, module landing pages, record-local navigation, and contextual command bars should handle the deeper product surface.

## Navigation Principle

Do not make the global navigation a complete product inventory.

Use this split:

```txt
Shell = product frame
Sidebar = top-level work areas
Header = search, create, context, notifications, AI, account
Module landing page = discover capabilities inside a work area
Work queue = daily operational surface
Record layout = local object navigation
Command bar = actions available for the current context
Right panel = contextual assistance, details, previews, and secondary workflows
Search = jump anywhere
Favorites = personal shortcuts
Saved views = recurring work queues
```

The result should be closer to Microsoft Azure's portal model than to a service-inventory console. Azure keeps a small global portal menu, strong global search, favorites, recent resources, dashboards, contextual resource menus, and command bars. Cognify should use the same pattern: keep the global shell stable and shallow, then let each module or record own its local complexity.

Reference patterns:

- Azure portal overview: `https://learn.microsoft.com/en-us/azure/azure-portal/azure-portal-overview`
- Azure favorites: `https://learn.microsoft.com/en-us/azure/azure-portal/azure-portal-add-remove-sort-favorites`
- Azure portal preferences: `https://learn.microsoft.com/en-us/azure/azure-portal/set-preferences`

## App Shells

Cognify should have a small number of shells. Do not create a new shell for every domain.

1. **Workspace Shell**
   - Main authenticated Cognify application for internal procurement users.
   - Owns tenant context, header, primary sidebar, breadcrumbs, command palette, notification host, right-panel host, footer, and mobile navigation.
   - This is the primary product shell and should remain the default for authenticated internal routes.
2. **Auth Shell**
   - Login, password reset, account recovery, and tenant selection.
   - No product sidebar, command palette, or procurement right panel.
3. **Vendor Portal Shell**
   - External vendor experience for RFQs, quotation submissions, document requests, collaboration, and vendor profile maintenance.
   - Uses simpler navigation and stricter separation from internal procurement surfaces.
4. **System/Public Shell**
   - System status, shared links, exported audit packs, or other public/system surfaces.
   - Should remain minimal and explicit.

## Workspace Shell Shape

```txt
+--------------------------------------------------------------+
| Header                                                       |
| Tenant / role | Breadcrumbs        Search  Create  AI  User  |
+---------------+----------------------------------------------+
| Primary Nav   | Page Content                                 |
|               |                                              |
| Home          | Page header / command bar                    |
| My Work       | Main table, dashboard, workflow, or record    |
| Procurement   |                                              |
| Vendors       | Optional local tabs / section nav             |
| Finance       |                                              |
| Evidence      |                                              |
| Analytics     | Optional right panel                          |
| Governance    |                                              |
| AI Assistant  |                                              |
| Admin         |                                              |
| Integrations  |                                              |
+---------------+----------------------------------------------+
| Footer / status, mostly quiet                                |
+--------------------------------------------------------------+
```

## Primary Sidebar

The primary sidebar should stay shallow and stable:

```txt
Home
My Work
Procurement
Vendors
Finance
Evidence
Analytics
Governance
AI Assistant
Admin
Integrations
```

Guidelines:

- Do not show all final product capabilities as nested sidebar items.
- Avoid disabled future roadmap items in primary navigation.
- Keep route visibility permission-aware.
- Use the command palette and global search for direct jumps.
- Use favorites and saved views for each user's frequent destinations.
- Use module landing pages to expose second-level surfaces.

## Workspace Layout Templates

The Workspace Shell should support a small set of page layout templates.

### Home Layout

Used for `/dashboard` or `/home`.

Primary purpose:

- Recent records.
- Pinned dashboards.
- Procurement calendar.
- Suggested next actions.
- High-priority work.

### Module Landing Layout

Used for module roots such as `/procurement`, `/vendors`, `/finance`, `/governance`, and `/analytics`.

Primary purpose:

- Grouped entry points inside the work area.
- Recent module records.
- Saved views.
- Common create or import actions.
- Lightweight module health or summary metrics.

Module landing pages replace deep global sidebar nesting.

### Work Queue Layout

Used for high-frequency operational views such as approvals, buyer intake, purchase order review, receiving, invoice exceptions, payment readiness, evidence review, and policy exceptions.

Primary purpose:

- Dense table-first workflows.
- Filters.
- Saved views.
- Bulk actions where permitted.
- SLA and status cues.

### Record Detail Layout

Used for requisitions, RFQs, quotations, vendors, projects, awards, purchase orders, receipts, invoices, payments, contracts, and similar durable records.

Primary purpose:

- Record header.
- Status and metadata.
- Contextual command bar.
- Local tabs or section anchors.
- Main record content.
- Record sidebar.
- Activity and audit history.

Current implementation uses `WorkflowStateLayout` in `apps/web/components/ui/workflow-state/record-workflow-layout.tsx`.

### Admin Settings Layout

Used for organization, users, policies, approval matrix, notification rules, feature flags, integration settings, billing, and sandbox settings.

Primary purpose:

- Local settings navigation inside the admin page area.
- Forms and policy editors.
- Permission-aware destructive or sensitive actions.

Admin settings navigation should not expand the global sidebar.

### Full-Canvas Layout

Used for comparison tables, scenario modeling, relationship graphs, analytics exploration, and other wide or high-density surfaces.

Primary purpose:

- More horizontal workspace.
- Focused command bar.
- Optional collapsible panels.
- Minimal nonessential chrome while staying inside the Workspace Shell.

## Final-State Module Hierarchy

The hierarchy below describes the mature product surface. It is not a sidebar spec. These items should appear through module landing pages, local record navigation, command palette/search, saved views, and contextual actions.

### Home

- Recent records.
- Pinned dashboards.
- Procurement calendar.
- Suggested next actions.
- High-priority work.

### My Work

- My approvals.
- My requisitions.
- Assigned buyer reviews.
- Purchase orders awaiting review.
- Receipts awaiting confirmation.
- Invoice exceptions.
- Payment-ready invoices.
- Evidence reviews.
- Policy exceptions.
- Overdue work.
- Delegated work.
- Saved personal queues.

### Procurement

- Requisitions.
  - All requisitions.
  - My drafts.
  - Submitted requests.
  - Change requests.
  - Templates.
  - Catalog suggestions.
- Projects.
  - All projects.
  - Project pipeline.
  - Budgets.
  - Risks.
  - Related awards.
- Buyer intake.
  - Intake queue.
  - Sourcing review.
  - Direct award candidates.
- Sourcing and RFQs.
  - RFQs.
  - Vendor invitations.
  - Response deadlines.
  - Strategic sourcing events.
  - Multi-round RFQs.
  - Negotiations.
  - Reverse auctions.
- Quotations.
  - Uploaded quotations.
  - Manual entries.
  - Versions.
  - OCR review.
  - Normalization.
  - Comparison tables.
  - Scoring matrices.
- Awards.
  - Recommendations.
  - Award decisions.
  - Award approvals.
  - Split awards.
  - Scenario modeling.
- Purchase orders.
  - Draft purchase orders.
  - Purchase order review.
  - Issued purchase orders.
  - Supplier acknowledgements.
  - Change orders.
  - Purchase order status.
- Receiving.
  - Pending receipts.
  - Partial receipts.
  - Goods receipts.
  - Service acceptance.
- Purchase order handoffs.
  - Draft handoffs.
  - Ready for export.
  - Export history.
- Procurement calendar.

### Finance

- Supplier invoices.
  - Invoice capture.
  - Invoice review.
  - Invoice attachments.
  - Duplicate invoice checks.
- Matching.
  - Two-way matching.
  - Three-way matching.
  - Match exceptions.
  - Tolerance review.
- Invoice approvals.
  - Pending approval.
  - Approved invoices.
  - Rejected invoices.
- Payments.
  - Payment readiness.
  - AP handoffs.
  - Payment status.
  - Remittance status.
- Adjustments.
  - Credit memos.
  - Debit notes.
  - Invoice reversals.
- Commitments.
  - Budget commitments.
  - Encumbrance relief.
  - Committed versus invoiced spend.

### Vendors

- Vendor directory.
- Contacts.
- Preferred vendors.
- Restricted vendors.
- Vendor documents.
- Vendor onboarding.
- Vendor prequalification.
- Vendor risk.
- Vendor performance.
- Supplier scorecards.
- Vendor collaboration.
- Vendor self-service review.
- Payable-ready vendor master.
- Remittance contacts.
- Tax and payment terms.
- Marketplace or vendor discovery.

### Evidence

- Evidence vault.
- Evidence classification.
- Document preview.
- Annotation.
- OCR review queue.
- Audit packs.
- Immutable decision log.
- Legal holds.
- Offline review packs.

### Analytics

- Executive summary.
- Spend analytics.
- Committed spend.
- Received spend.
- Invoiced spend.
- Paid spend.
- Cycle time.
- Savings and avoidance.
- Compliance.
- Budget forecasting.
- Demand aggregation.
- Price benchmarking.
- Category strategy.
- Supplier diversity.
- ESG and sustainability.
- Procurement health.
- Cross-record relationship graph.
- Benchmarking across tenants.
- Natural language reporting.

### Governance

- Policy rules.
- Policy exceptions.
- Conflict declarations.
- Budget thresholds.
- Matching tolerances.
- Payment controls.
- Required evidence.
- Category management.
- Preferred vendor controls.
- Custom fields.
- Conditional forms.
- Risk alerts.
- Fraud and anomaly detection.
- Duplicate requisition detection.
- Vendor risk scoring.
- Requisition risk scoring.
- Award risk scoring.
- Retention policies.
- Field-level sensitivity.

### AI Assistant

- Contextual assistant panel.
- Guided buying.
- Conversational request creation.
- Procurement knowledge base.
- AI requisition quality checks.
- AI quotation summaries.
- AI vendor comparison narratives.
- AI risk explanations.
- Semantic evidence search.
- Predictive approval bottlenecks.
- Smart reminder scheduling.

### Contracts

Contracts may begin as part of Evidence, Vendors, or Procurement. If the surface grows enough, it can become its own module.

- Contract repository.
- Renewals.
- Clause extraction.
- Obligation tracking.
- Contract lifecycle management handoffs.
- E-signature status.

### Admin

- Organization structure.
- Users and roles.
- Access reviews.
- Approval matrix.
- Procurement policy administration.
- Notification rules.
- Feature flags.
- Admin audit console.
- Observability.
- Usage analytics.
- Billing and plan management.
- Data export and portability.
- Sandbox tenant.

### Integrations

- ERP export.
- ERP integrations.
- Accounting integrations.
- Inventory or asset system integrations.
- Contract lifecycle management integrations.
- E-signature integrations.
- External risk data.
- Vendor master data sync.
- External P2P status sync.
- Email intake.
- Webhooks.
- Public API.
- Import tools.
- Export tools.

## Record-Local Navigation Examples

Record-local navigation belongs inside the record page, not in the global sidebar.

### Requisition Detail

```txt
Overview
Line items
Evidence
Approvals
RFQs / quotations
Award
Activity
Risk / policy
```

### Purchase Order Detail

```txt
Overview
Lines
Supplier
Approvals
Issue history
Receiving
Invoices
Change orders
Activity
```

### Invoice Detail

```txt
Overview
Lines
PO / receipt matching
Exceptions
Approvals
Payment readiness
Attachments
Activity
```

### Vendor Detail

```txt
Overview
Contacts
Documents
Risk
Performance
RFQs
Awards
Activity
```

### RFQ Detail

```txt
Overview
Vendors
Responses
Normalization
Comparison
Scoring
Recommendation
Activity
```

## Vendor Portal Navigation

The vendor portal should use a separate shell with a smaller navigation set.

```txt
RFQ Invitations
Active RFQs
Submitted Quotations
Document Requests
Profile
Contacts
Certifications
Purchase Orders
Invoices
Collaboration Threads
```

## Implementation Guardrails

- Keep Cognify-specific shell, navigation, and workflow composition in `apps/web`.
- Keep reusable primitive components in `packages/ui`.
- Keep the shell registry permission-aware.
- Prefer landing pages, saved views, and search over deep sidebar nesting.
- Prefer local record navigation for durable record sections.
- Prefer command bars for contextual actions.
- Do not expose roadmap-only future features as disabled primary navigation items.
- Add new top-level sidebar items only when the destination represents a durable work area used across multiple workflows.
- If a module has fewer than two real destinations, link directly to the current surface instead of creating an empty module landing page.
