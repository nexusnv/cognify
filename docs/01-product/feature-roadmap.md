# Cognify Feature Roadmap

## Changelog

- 2026-05-12: Added implementation grouping, ownership mapping, and parallelization guidance.
- 2026-05-11: Initial comprehensive feature inventory and implementation-priority roadmap.

## Purpose

This document lists candidate features for Cognify, grouped by the order they should generally be implemented. It is not a commitment to build every feature immediately. It is a product planning inventory for turning Cognify from a requisition workflow into a multi-tenant, AI-assisted procurement governance platform.

Feature specs should still be written before implementation. This roadmap helps choose the next feature slice and gives each candidate enough context to understand why it matters.

## Prioritization Model

Priority is ordered by product dependency, customer value, implementation leverage, and enterprise readiness:

1. **P0 - Platform and workflow foundation**: required before meaningful procurement workflows can scale.
2. **P1 - Core procurement lifecycle**: requisitions, approvals, sourcing, quotations, comparison, and awards.
3. **P2 - Governance, risk, audit, and AI assistance**: trust, decision quality, policy enforcement, and automation.
4. **P3 - Enterprise operations and integrations**: administration, external systems, finance controls, and advanced security.
5. **P4 - Optimization, intelligence, and marketplace expansion**: advanced insights, supplier ecosystems, benchmarking, and strategic procurement.

## Status Assessment

Feature status below is assessed against the current repository state as of 2026-05-20, using shipped routes, tests, generated contracts, and committed spec/plan artifacts.

## P0 - Platform and Workflow Foundation

These features establish the tenant-scoped SaaS base, common UX shell, auditability, and workflow spine. They should be built before broad procurement modules to avoid rework.

| Feature Number | Feature Name | Feature Description | Feature Status | Design Spec | Implementation Plan | PR Number | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| P0-01 | Multi-Tenant Account Model | Define tenants, memberships, tenant-scoped data access, tenant switching, and tenant-aware API guards. This is the foundation for serving multiple companies from one Cognify deployment while keeping data isolated. | Fully Implemented | 2026-05-09-cognify-greenfield-saas-runbook-design.md<br>2026-05-12-tenant-auth-access-foundation-design.md | 2026-05-09-cognify-greenfield-saas-scaffold.md<br>2026-05-12-tenant-auth-access-foundation.md |  | Tenant memberships, switching, and tenant-scoped guards are in the shipped identity foundation. |
| P0-02 | Authentication | Support login, logout, password reset, session handling, and secure API authentication. The first version can use email/password and Laravel Sanctum, with SSO added later for enterprise customers. | Fully Implemented | 2026-05-12-tenant-auth-access-foundation-design.md | 2026-05-12-tenant-auth-access-foundation.md |  |  |
| P0-03 | User Profile and Preferences | Allow users to manage name, avatar, locale, timezone, notification preferences, theme, and default workspace settings. This improves day-to-day usability and gives later workflow features reliable user context. | Partially Implemented | 2026-05-12-tenant-auth-access-foundation-design.md<br>2026-05-14-notification-foundation-design.md | 2026-05-12-tenant-auth-access-foundation.md<br>2026-05-14-notification-foundation.md |  | Profile, avatar, locale, timezone, theme, and notification preferences ship; default workspace settings are not evident yet. |
| P0-04 | Role and Permission Baseline | Introduce core roles such as requester, buyer, approver, finance, auditor, vendor manager, and admin. Permissions should be explicit and tenant-scoped so later modules can add fine-grained controls without rewriting authorization. | Fully Implemented | 2026-05-12-tenant-auth-access-foundation-design.md | 2026-05-12-tenant-auth-access-foundation.md |  |  |
| P0-05 | Workspace Shell | Provide the application shell for operational work: sidebar navigation, sticky header, breadcrumbs, footer status, command palette host, notification host, and contextual right panel. Most later features will reuse this shell. | Fully Implemented | 2026-05-12-app-shell-foundation-design.md | 2026-05-12-app-shell-foundation.md |  |  |
| P0-06 | Workspace Detail Layout | Create a record-focused layout for requisitions, projects, quotations, vendors, approvals, and awards. It should support contextual sidebars, activity timelines, local navigation, right-side panels, and state-aware actions. | Fully Implemented | 2026-05-13-workflow-ui-primitives-design.md | 2026-05-13-workflow-ui-primitives.md |  |  |
| P0-07 | Global Right Panel System | Implement one reusable system for contextual panels such as AI insights, quick edit, approval history, vendor risk, evidence preview, and quotation details. A shared panel host avoids every feature inventing its own drawer behavior. | Fully Implemented | 2026-05-13-workflow-ui-primitives-design.md | 2026-05-13-workflow-ui-primitives.md |  |  |
| P0-08 | Command Palette | Add keyboard-first navigation and actions such as creating requisitions, opening approvals, searching vendors, or jumping to recent records. This reinforces Cognify as a fast operational tool instead of a static dashboard. | Fully Implemented | 2026-05-14-search-command-foundation-design.md | 2026-05-14-search-command-foundation.md |  |  |
| P0-09 | Global Search | Provide tenant-scoped search across requisitions, projects, vendors, quotations, approvals, awards, and evidence. Early search can be keyword-based; later versions can add semantic and permission-aware AI search. | Partially Implemented | 2026-05-14-search-command-foundation-design.md | 2026-05-14-search-command-foundation.md |  | Tenant-scoped API and shell search exist, but the roadmap target still mentions approvals and evidence search that are not yet present. |
| P0-10 | Notification Center | Display workflow notifications for assigned approvals, returned requisitions, vendor responses, risk alerts, SLA breaches, and system events. Notifications should include read/unread state and deep links to affected records. | Fully Implemented | 2026-05-14-notification-foundation-design.md | 2026-05-14-notification-foundation.md |  |  |
| P0-11 | Activity Timeline Primitive | Create a consistent timeline component for audit-visible record events: created, updated, submitted, approved, rejected, commented, uploaded, scored, and awarded. This gives every workflow a trustworthy history. | Fully Implemented | 2026-05-13-workflow-ui-primitives-design.md | 2026-05-13-workflow-ui-primitives.md |  |  |
| P0-12 | Audit Event Infrastructure | Capture immutable tenant-scoped audit events with actor, action, target, timestamp, metadata, and before/after context where appropriate. Auditability is central to procurement governance and should not be bolted on late. | Fully Implemented | 2026-05-12-audit-api-contract-foundation-design.md | 2026-05-12-audit-api-contract-foundation.md |  |  |
| P0-13 | File Attachment Baseline | Support secure uploads, downloads, previews, metadata, file type validation, and tenant-scoped storage. Attachments are needed for requisition evidence, quotations, contracts, vendor documents, invoices, and audit packs. | Fully Implemented | 2026-05-14-file-attachment-baseline-design.md | 2026-05-14-file-attachment-baseline.md |  |  |
| P0-14 | Data Table Foundation | Create reusable table patterns for dense procurement data: sorting, filtering, pagination, saved views later, row actions, column sizing, loading states, empty states, and mobile list fallbacks. | Fully Implemented | 2026-05-13-workflow-ui-primitives-design.md | 2026-05-13-workflow-ui-primitives.md |  |  |
| P0-15 | Form and Validation Foundation | Standardize forms with inline errors, error summaries, save states, unsaved-change guards, keyboard accessibility, and API validation mapping. Procurement workflows are form-heavy, so this needs to be consistent early. | Fully Implemented | 2026-05-13-workflow-ui-primitives-design.md<br>2026-05-15-requisition-authoring-intake-foundation-design.md | 2026-05-13-workflow-ui-primitives.md<br>2026-05-15-requisition-authoring-intake-foundation.md |  |  |
| P0-16 | Status Badge and Workflow State System | Define how statuses render and how transitions are named across domains. Badges should include text, not rely on color alone, and map cleanly to backend state machines. | Fully Implemented | 2026-05-13-workflow-ui-primitives-design.md | 2026-05-13-workflow-ui-primitives.md |  |  |
| P0-17 | API Error Contract | Standardize validation errors, authorization errors, not-found errors, conflict errors, and transient failures. A reliable error contract keeps frontend workflow recovery predictable. | Fully Implemented | 2026-05-12-audit-api-contract-foundation-design.md | 2026-05-12-audit-api-contract-foundation.md |  |  |
| P0-18 | OpenAPI and Generated Client Workflow | Keep backend API contracts documented through OpenAPI and regenerate typed clients for frontend consumers. This reduces drift between Laravel and Next.js as feature count grows. | Fully Implemented | 2026-05-12-audit-api-contract-foundation-design.md | 2026-05-12-audit-api-contract-foundation.md |  |  |
| P0-19 | Seed and Demo Data | Provide realistic tenant, user, vendor, requisition, quotation, approval, and activity data for local development and demos. Good demo data makes workflow testing and product review much faster. | Fully Implemented | 2026-05-15-local-demo-system-readiness-design.md | 2026-05-15-local-demo-system-readiness-implementation.md |  |  |
| P0-20 | Environment and System Status Surface | Expose app version, API health, queue health, storage health, and system status in admin/debug surfaces. This helps developers and operators understand whether local or deployed environments are healthy. | Fully Implemented | 2026-05-15-local-demo-system-readiness-design.md | 2026-05-15-local-demo-system-readiness-implementation.md |  |  |

## P1 - Core Procurement Lifecycle

These features create the main user-facing procurement journey from request to award. They should be implemented in thin, complete slices that are usable end to end.

| Feature Number | Feature Name | Feature Description | Feature Status | Design Spec | Implementation Plan | PR Number | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| P1-01 | Requisition Draft Creation | Allow requesters to create procurement requisitions with title, justification, needed-by date, line items, estimated pricing, department, project, cost center, and optional context. This is the root object for most procurement workflows. | Fully Implemented | 2026-05-10-requisition-draft-submission-design.md<br>2026-05-15-requisition-authoring-intake-foundation-design.md | 2026-05-10-requisition-draft-submission-implementation.md<br>2026-05-15-requisition-authoring-intake-foundation.md |  |  |
| P1-02 | Requisition Autosave and Manual Save | Support reliable draft persistence with visible save state, conflict handling, and unsaved-change protection. Requesters often build requisitions gradually, so losing work would erode trust quickly. | Fully Implemented | 2026-05-10-requisition-draft-submission-design.md<br>2026-05-15-requisition-authoring-intake-foundation-design.md | 2026-05-10-requisition-draft-submission-implementation.md<br>2026-05-15-requisition-authoring-intake-foundation.md |  |  |
| P1-03 | Requisition Submission | Let requesters submit complete drafts into the formal procurement workflow. Submission should validate required fields, lock requester edits where appropriate, and record an audit event. | Fully Implemented | 2026-05-15-requisition-submission-workspace-design.md | 2026-05-15-requisition-submission-workspace.md |  |  |
| P1-04 | Requisition List and Work Queue | Provide an operational list of requisitions with filters for status, requester, owner, department, needed-by date, amount, and updated date. This becomes the requester's and buyer's daily work surface. | Fully Implemented | 2026-05-15-requisition-submission-workspace-design.md | 2026-05-15-requisition-submission-workspace.md |  |  |
| P1-05 | Requisition Detail Workspace | Show the full requisition record with overview, line items, justification, submission checklist, activity, approvals, quotation readiness, and related evidence. The detail page should be the durable source of truth for a request. | Fully Implemented | 2026-05-15-requisition-submission-workspace-design.md | 2026-05-15-requisition-submission-workspace.md |  |  |
| P1-06 | Requisition Comments and Mentions | Allow users to discuss requisitions in context, mention colleagues, and preserve decisions beside the record. Comments reduce procurement decisions happening in disconnected email threads. | Fully Implemented | 2026-05-15-requisition-submission-workspace-design.md | 2026-05-15-requisition-submission-workspace.md |  |  |
| P1-07 | Requisition Change Requests | Let buyers or approvers return a requisition for clarification with specific requested changes. Requesters should see what needs attention and resubmit after editing. | Fully Implemented | 2026-05-15-requisition-submission-workspace-design.md<br>2026-05-17-approval-orchestration-design.md | 2026-05-15-requisition-submission-workspace.md<br>2026-05-17-approval-orchestration.md |  |  |
| P1-08 | Requisition Cancellation and Withdrawal | Support requester withdrawal and admin cancellation with reason capture and audit events. Procurement workflows need controlled ways to stop stale or invalid requests. | Fully Implemented | 2026-05-15-requisition-submission-workspace-design.md | 2026-05-15-requisition-submission-workspace.md |  |  |
| P1-09 | Requisition Templates | Offer reusable templates for common buying patterns such as SaaS subscription, IT equipment, professional services, office supplies, and facilities work. Templates reduce form friction and improve data consistency. | Fully Implemented | 2026-05-15-requisition-authoring-intake-foundation-design.md | 2026-05-15-requisition-authoring-intake-foundation.md |  |  |
| P1-10 | Line Item Catalog Suggestions | Suggest common items, units, categories, and estimated pricing while the requester edits line items. This improves data quality without requiring a full catalog system at the start. | Fully Implemented | 2026-05-15-requisition-authoring-intake-foundation-design.md | 2026-05-15-requisition-authoring-intake-foundation.md |  |  |
| P1-11 | Procurement Project Records | Group related requisitions, quotations, approvals, budgets, and awards under a project. Projects help teams manage larger initiatives such as office expansion, software renewal, or construction procurement. | Fully Implemented | 2026-05-15-procurement-project-workspace-design.md | 2026-05-15-procurement-project-workspace.md |  |  |
| P1-12 | Project Workspace | Provide a project-level view with charter, budget, requisition pipeline, approvals, activity, risks, and related awards. This is useful for managers and buyers overseeing multi-step procurement work. | Fully Implemented | 2026-05-15-procurement-project-workspace-design.md | 2026-05-15-procurement-project-workspace.md |  |  |
| P1-13 | Department and Cost Center Capture | Track organizational ownership for spend requests. This enables approval routing, reporting, budget checks, and finance export later. | Fully Implemented | 2026-05-15-requisition-authoring-intake-foundation-design.md | 2026-05-15-requisition-authoring-intake-foundation.md |  |  |
| P1-14 | Approval Routing Rules | Route submitted requisitions to the correct approvers based on amount, department, category, cost center, project, requester, risk, or vendor. Rules should be configurable and versioned. | Fully Implemented | 2026-05-17-approval-orchestration-design.md | 2026-05-17-approval-orchestration.md |  |  |
| P1-15 | Approval Tasks | Create actionable approval tasks with approve, reject, request changes, delegate, and comment actions. Approval tasks should be visible in an approver work queue and in the record activity timeline. | Fully Implemented | 2026-05-17-approval-orchestration-design.md | 2026-05-17-approval-orchestration.md |  |  |
| P1-16 | Sequential Approval Chains | Support ordered approvals, such as manager approval followed by finance approval followed by procurement approval. Sequential chains are common when spend authority depends on hierarchy. | Fully Implemented | 2026-05-17-approval-orchestration-design.md | 2026-05-17-approval-orchestration.md |  |  |
| P1-17 | Parallel Approval Groups | Support approvals where multiple stakeholders can approve in parallel, such as legal and IT security reviewing the same software purchase. This reduces cycle time while preserving governance. | Fully Implemented | 2026-05-17-approval-orchestration-design.md | 2026-05-17-approval-orchestration.md |  |  |
| P1-18 | Approval Delegation | Allow an approver to delegate temporarily or permanently within policy limits. Delegation prevents procurement from blocking during leave or role changes. | Fully Implemented | 2026-05-17-approval-orchestration-design.md | 2026-05-17-approval-orchestration.md |  |  |
| P1-19 | Approval Escalation | Escalate overdue approval tasks to managers or fallback approvers based on SLA rules. This keeps requests moving and gives operators visibility into bottlenecks. | Fully Implemented | 2026-05-17-approval-orchestration-design.md | 2026-05-17-approval-orchestration.md |  |  |
| P1-20 | Approval SLA Tracking | Track time spent in approval stages, overdue approvals, and aging requests. SLA metrics help procurement teams identify process delays. | Fully Implemented | 2026-05-17-approval-orchestration-design.md | 2026-05-17-approval-orchestration.md |  |  |
| P1-21 | Approval Policy Preview | Show requesters and buyers which approval path a requisition will follow before submission or routing. This improves transparency and reduces surprise approvals. | Fully Implemented | 2026-05-17-approval-orchestration-design.md | 2026-05-17-approval-orchestration.md |  |  |
| P1-22 | Buyer Intake Review | Give buyers a queue for submitted requisitions that need sourcing review. Buyers can verify completeness, classify category, decide sourcing path, and start RFQ or direct award workflows. | Fully Implemented | 2026-05-19-buyer-intake-sourcing-review-design.md | 2026-05-19-buyer-intake-sourcing-review.md |  |  |
| P1-23 | RFQ Creation | Allow buyers to create a request for quotation from a requisition or project. RFQs should include scope, line items, due date, invited vendors, required documents, and response instructions. | Fully Implemented | 2026-05-19-rfq-draft-creation-design.md | 2026-05-19-rfq-draft-creation.md |  |  |
| P1-24 | Vendor Invitation to RFQ | Invite selected vendors to submit quotations for an RFQ. The system should track invitation status, response deadlines, and communication history. | Fully Implemented | 2026-05-19-vendor-invitation-to-rfq-design.md | 2026-05-19-vendor-invitation-to-rfq.md |  |  |
| P1-25 | Vendor Portal Baseline | Provide a secure external vendor experience for viewing RFQs and submitting quotation responses. Early vendor portal scope can be narrow, but it should preserve tenant isolation and audit history. | Fully Implemented | 2026-05-20-vendor-portal-baseline-design.md | 2026-05-20-vendor-portal-baseline.md |  |  |
| P1-26 | Quotation Upload | Allow buyers or vendors to upload quotation files against an RFQ or requisition. Uploaded quotations become evidence and input for extraction, normalization, comparison, and award decisions. | Not Implemented |  |  |  | Explicitly left out of the 2026-05-20 vendor portal baseline scope. |
| P1-27 | Quotation Manual Entry | Support structured quotation entry when a vendor responds outside the portal or submits incomplete documents. Manual entry keeps the workflow usable before full OCR automation exists. | Not Implemented |  |  |  |  |
| P1-28 | Quotation Versioning | Track revised quotations and preserve prior versions. Procurement comparisons must show which vendor price and terms were evaluated at decision time. | Not Implemented |  |  |  |  |
| P1-29 | Quotation Normalization | Normalize quotation data into comparable fields such as item, quantity, unit price, currency, tax, freight, discount, payment terms, warranty, lead time, and compliance notes. This is required before meaningful comparison. | Not Implemented |  |  |  |  |
| P1-30 | Quotation Comparison Table | Compare vendors side by side by price, delivery, terms, compliance, risk, and qualitative notes. This should be a central buyer workspace rather than an exported spreadsheet. | Not Implemented |  |  |  |  |
| P1-31 | Vendor Scoring Matrix | Score vendor responses using configurable criteria such as cost, delivery, quality, compliance, risk, sustainability, and past performance. Scores help explain award recommendations. | Not Implemented |  |  |  |  |
| P1-32 | Recommendation and Award Decision | Let buyers select a recommended vendor, explain the rationale, attach supporting evidence, and route the decision for approval if required. The award decision should be auditable. | Not Implemented |  |  |  |  |
| P1-33 | Award Approval | Route final award recommendations for approval when policy requires it. This can differ from requisition approval because stakeholders may evaluate vendor selection rather than spend request validity. | Not Implemented |  |  |  |  |
| P1-34 | Purchase Order Request Handoff | Generate a structured handoff for ERP or finance systems after award approval. Even before direct ERP integration, Cognify should make the next operational step clear. | Not Implemented |  |  |  |  |
| P1-35 | Procurement Calendar | Show RFQ deadlines, approval due dates, expected delivery dates, contract renewal dates, and expiring vendor documents. Calendar visibility helps teams manage time-sensitive work. | Not Implemented |  |  |  |  |

## P2 - Governance, Risk, Audit, and AI Assistance

These features make Cognify meaningfully different from a generic procurement tracker. They improve decision quality, compliance, transparency, and automation.

| Feature Number | Feature Name | Feature Description | Feature Status | Design Spec | Implementation Plan | PR Number | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| P2-01 | Evidence Vault | Create a governed repository for procurement evidence: requisition documents, quotations, comparison exports, approvals, vendor certificates, contracts, risk reports, and audit packs. Evidence should be attached to records and searchable by permission. | Not Implemented |  |  |  |  |
| P2-02 | Evidence Classification | Classify files by type, source, related record, sensitivity, and retention requirement. Classification supports compliance workflows and later AI extraction. | Not Implemented |  |  |  |  |
| P2-03 | Evidence Preview and Annotation | Allow users to preview documents, highlight relevant sections, and add notes. This is useful when reviewers need to understand why a quotation or contract was accepted. | Not Implemented |  |  |  |  |
| P2-04 | OCR Extraction Pipeline | Extract structured data from uploaded quotations, invoices, vendor documents, and contracts. OCR should create reviewable suggestions rather than silently overwriting procurement records. | Not Implemented |  |  |  |  |
| P2-05 | OCR Review Queue | Give users a queue to verify extracted fields, correct mistakes, and approve normalized data. Human review is important because procurement documents vary widely. | Not Implemented |  |  |  |  |
| P2-06 | AI Extraction Confidence | Show confidence levels for extracted fields and require review when confidence is low. This keeps AI assistance transparent and reduces the risk of incorrect automation. | Not Implemented |  |  |  |  |
| P2-07 | AI Procurement Assistant Panel | Provide contextual assistance inside requisitions, RFQs, quotations, vendor profiles, and approvals. The assistant can summarize records, identify missing information, explain policy impact, and suggest next actions. | Not Implemented |  |  |  |  |
| P2-08 | AI Requisition Quality Check | Review drafts for missing business justification, vague scope, weak line item detail, unclear needed-by dates, and policy concerns. The goal is to improve submissions before they enter approval. | Not Implemented |  |  |  |  |
| P2-09 | AI Quotation Summary | Summarize uploaded quotations, including price, delivery terms, exclusions, payment terms, warranty, and compliance deviations. This reduces review time for buyers and approvers. | Not Implemented |  |  |  |  |
| P2-10 | AI Vendor Comparison Narrative | Generate a plain-language explanation of differences between vendors and why a recommendation is stronger or weaker. The narrative should cite structured data and evidence, not operate as a black box. | Not Implemented |  |  |  |  |
| P2-11 | AI Risk Explanation | Explain why a requisition, vendor, or award is flagged as risky, with references to detected anomalies, policy rules, missing documents, or historical patterns. This improves trust in risk scoring. | Not Implemented |  |  |  |  |
| P2-12 | Fraud and Anomaly Detection | Detect unusual spend patterns, repeated awards to the same vendor, split purchases, last-minute vendor changes, price outliers, duplicate quotations, suspicious timing, and unusual approval paths. Alerts should be explainable and reviewable. | Not Implemented |  |  |  |  |
| P2-13 | Duplicate Requisition Detection | Identify likely duplicate or overlapping requisitions using title, requester, line items, needed-by date, project, and vendor context. This prevents accidental duplicate spend. | Not Implemented |  |  |  |  |
| P2-14 | Budget Threshold Warnings | Warn when estimated or awarded spend exceeds configured thresholds. These warnings can trigger additional approvals or require justification. | Not Implemented |  |  |  |  |
| P2-15 | Policy Rule Engine | Define procurement policies such as minimum quote count, competitive bidding thresholds, preferred vendor rules, restricted categories, approval limits, and required evidence. Policy checks should run before submission, award, and export. | Not Implemented |  |  |  |  |
| P2-16 | Policy Exception Workflow | Allow users to request and approve exceptions with reason, evidence, approver, expiry, and audit trail. Exceptions are common in real procurement and must be controlled rather than hidden. | Not Implemented |  |  |  |  |
| P2-17 | Conflict of Interest Declarations | Require users to declare potential conflicts when approving or awarding certain vendors. The declaration should be attached to the decision history. | Not Implemented |  |  |  |  |
| P2-18 | Vendor Risk Scoring | Score vendors based on documents, compliance status, past performance, financial risk, geography, sanctions checks later, and procurement history. Scores should be explainable and versioned. | Not Implemented |  |  |  |  |
| P2-19 | Requisition Risk Scoring | Score requisitions based on amount, urgency, category, vendor concentration, policy exceptions, missing evidence, and historical patterns. This helps buyers and approvers focus on risky work. | Not Implemented |  |  |  |  |
| P2-20 | Award Risk Scoring | Score award recommendations based on vendor selection, quote spread, exception count, compliance gaps, and anomaly signals. This is especially important for governance and audit reviews. | Not Implemented |  |  |  |  |
| P2-21 | Audit Pack Generation | Generate a complete audit pack for a requisition, RFQ, award, or project containing evidence, approvals, comments, policy checks, risk explanations, and final decision rationale. This reduces audit preparation effort. | Not Implemented |  |  |  |  |
| P2-22 | Immutable Decision Log | Preserve key procurement decisions in a tamper-evident log. This should include submit, approve, reject, award, exception, and export events. | Not Implemented |  |  |  |  |
| P2-23 | Saved Views | Allow users to save table filters and columns for work queues such as "My approvals", "High-risk awards", "Overdue RFQs", or "Pending evidence review". Saved views improve daily operational speed. | Not Implemented |  |  |  |  |
| P2-24 | Custom Fields | Let tenant admins define controlled additional fields for requisitions, vendors, projects, and awards. Custom fields help Cognify fit different procurement organizations without forking the product. | Not Implemented |  |  |  |  |
| P2-25 | Conditional Forms | Show fields or required evidence based on category, amount, department, vendor type, or policy. This keeps forms lighter while still enforcing governance. | Not Implemented |  |  |  |  |
| P2-26 | Category Management | Classify spend into procurement categories and subcategories. Categories can drive templates, approval routing, preferred vendors, policy requirements, and reporting. | Not Implemented |  |  |  |  |
| P2-27 | Preferred Vendor Controls | Mark vendors as preferred, conditional, restricted, or blocked by category or department. Users should see policy guidance when selecting vendors. | Not Implemented |  |  |  |  |
| P2-28 | Vendor Performance Tracking | Track on-time delivery, quality issues, responsiveness, award history, dispute history, and buyer feedback. Performance becomes part of future vendor comparison and risk scoring. | Not Implemented |  |  |  |  |
| P2-29 | Supplier Document Management | Track vendor certificates, insurance, tax documents, compliance forms, security documents, and expiry dates. Missing or expired documents should block or warn during award depending on policy. | Not Implemented |  |  |  |  |
| P2-30 | Renewal and Contract Alerts | Notify teams before contracts, certificates, subscriptions, or vendor documents expire. This helps procurement become proactive rather than reactive. | Not Implemented |  |  |  |  |
| P2-31 | Spend Analytics Dashboard | Show spend by vendor, category, department, requester, project, status, and time period. Spend visibility is a core enterprise procurement value. | Not Implemented |  |  |  |  |
| P2-32 | Cycle Time Analytics | Measure how long requisitions, approvals, RFQs, comparisons, and awards take. This reveals where procurement operations are slow. | Not Implemented |  |  |  |  |
| P2-33 | Savings and Avoidance Tracking | Track estimated versus awarded spend, negotiated savings, avoided spend, and budget impact. These metrics help procurement demonstrate business value. | Not Implemented |  |  |  |  |
| P2-34 | Compliance Dashboard | Show quote count compliance, policy exception rates, missing evidence, overdue approvals, high-risk awards, and audit readiness. This supports procurement governance leaders. | Not Implemented |  |  |  |  |
| P2-35 | Executive Summary Dashboard | Provide leadership-level procurement metrics: active spend pipeline, approved spend, savings, risk exposure, cycle time, and major pending decisions. This should be concise and trend-oriented. | Not Implemented |  |  |  |  |

## P3 - Enterprise Operations and Integrations

These features support larger customers, finance workflows, security requirements, and operational administration.

| Feature Number | Feature Name | Feature Description | Feature Status | Design Spec | Implementation Plan | PR Number | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| P3-01 | Organization Structure Management | Model departments, teams, business units, locations, and reporting lines. Organization structure improves routing, permissions, analytics, and spend ownership. | Not Implemented |  |  |  |  |
| P3-02 | Approval Matrix Administration | Give admins a UI to configure approval thresholds, approver groups, routing conditions, delegations, and escalation rules. This reduces developer involvement in policy changes. | Fully Implemented | 2026-05-17-approval-orchestration-design.md | 2026-05-17-approval-orchestration.md |  |  |
| P3-03 | Procurement Policy Administration | Allow admins to configure quote requirements, category rules, evidence requirements, preferred vendor policies, restricted vendors, and exception rules. Policy should be editable with version history. | Not Implemented |  |  |  |  |
| P3-04 | User Provisioning | Support inviting users, deactivating users, role assignment, team membership, and tenant access review. This is necessary for enterprise account administration. | Partially Implemented | 2026-05-12-tenant-auth-access-foundation-design.md | 2026-05-12-tenant-auth-access-foundation.md |  | Tenant membership and role resolution exist, but invite/deactivate/access-review workflows are not evident. |
| P3-05 | Single Sign-On | Support SAML or OIDC SSO for enterprise customers. SSO reduces password management risk and aligns with corporate identity providers. | Not Implemented |  |  |  |  |
| P3-06 | SCIM Provisioning | Support automated user and group provisioning from identity providers. SCIM helps large organizations keep access current as employees join, move, or leave. | Not Implemented |  |  |  |  |
| P3-07 | Fine-Grained Access Control | Add permissions by module, record ownership, department, category, amount, and role. Some procurement records may be sensitive and should not be visible to all tenant members. | Partially Implemented | 2026-05-12-tenant-auth-access-foundation-design.md<br>2026-05-17-approval-orchestration-design.md | 2026-05-12-tenant-auth-access-foundation.md<br>2026-05-17-approval-orchestration.md |  | Tenant-scoped permissions and record-level workflow checks exist, but the roadmap target goes beyond the current role baseline. |
| P3-08 | Field-Level Sensitivity | Protect sensitive fields such as financial details, vendor bank data, confidential legal notes, or risk findings. Field-level controls help support regulated or high-sensitivity procurement. | Not Implemented |  |  |  |  |
| P3-09 | Data Retention Policies | Configure how long audit events, attachments, quotations, contracts, and procurement records are retained. Retention policies matter for compliance, storage cost, and privacy. | Not Implemented |  |  |  |  |
| P3-10 | Legal Hold | Prevent deletion or retention expiry for records under investigation or audit. Legal hold should be explicit and auditable. | Not Implemented |  |  |  |  |
| P3-11 | Advanced Notifications | Support email, in-app notifications, digest emails, reminder schedules, escalation alerts, and user preference controls. Notification channels should be configurable per event type. | Partially Implemented | 2026-05-14-notification-foundation-design.md | 2026-05-14-notification-foundation.md |  | In-app notifications and preference controls ship; digest email, reminder scheduling, and multi-channel delivery are not evident. |
| P3-12 | Email Intake | Allow vendors or internal users to send quotations and procurement evidence to a controlled email address that links files to the correct RFQ or requisition. This accommodates real-world vendor behavior. | Not Implemented |  |  |  |  |
| P3-13 | ERP Export | Export approved awards or purchase order requests to ERP-compatible formats. Early versions can support CSV or JSON export before direct integrations. | Not Implemented |  |  |  |  |
| P3-14 | ERP Integration | Integrate with systems such as NetSuite, SAP, Oracle, Microsoft Dynamics, or custom finance systems. This allows approved procurement decisions to flow into purchasing and accounting operations. | Not Implemented |  |  |  |  |
| P3-15 | Accounting Integration | Connect procurement data to accounting workflows for cost centers, budget codes, vendor master data, and purchase order status. This reduces double entry. | Not Implemented |  |  |  |  |
| P3-16 | Inventory or Asset System Integration | Send awarded equipment or asset purchases to downstream asset systems. This is useful for IT equipment, facilities assets, and operational inventory. | Not Implemented |  |  |  |  |
| P3-17 | Contract Lifecycle Management Integration | Integrate with CLM systems or provide handoff exports for contracts that must be drafted, reviewed, or signed after award. Procurement often crosses into legal workflows. | Not Implemented |  |  |  |  |
| P3-18 | E-Signature Integration | Support e-signature providers for vendor agreements, award letters, declarations, or contract documents. Signed documents should return to the evidence vault. | Not Implemented |  |  |  |  |
| P3-19 | External Risk Data Integrations | Integrate vendor checks such as sanctions, adverse media, financial risk, cybersecurity posture, and ESG data. These signals can feed vendor and award risk scores. | Not Implemented |  |  |  |  |
| P3-20 | Vendor Master Data Sync | Synchronize vendors with finance or ERP master data systems. This reduces duplicate vendors and ensures awards reference approved supplier records. | Not Implemented |  |  |  |  |
| P3-21 | Purchase Order Status Sync | Pull purchase order numbers and status back into Cognify after finance processing. Users should know whether an award has become an operational purchase order. | Not Implemented |  |  |  |  |
| P3-22 | Webhooks | Expose webhooks for events such as requisition submitted, approval completed, RFQ closed, award approved, and risk flagged. Webhooks enable customer-specific automation. | Not Implemented |  |  |  |  |
| P3-23 | Public API | Provide documented tenant-scoped APIs for customers and integrators. Public APIs should include authentication, rate limiting, audit events, and stable versioning. | Partially Implemented | 2026-05-12-audit-api-contract-foundation-design.md | 2026-05-12-audit-api-contract-foundation.md |  | The product has documented internal OpenAPI endpoints and generated clients, but no separate public API program is defined. |
| P3-24 | Import Tools | Support importing vendors, categories, cost centers, departments, historical spend, and open requisitions. Imports accelerate onboarding and migration from spreadsheets. | Not Implemented |  |  |  |  |
| P3-25 | Bulk Actions | Allow permitted users to bulk assign, close, tag, export, remind, or archive records. Bulk operations should show confirmation and audit outcomes. | Not Implemented |  |  |  |  |
| P3-26 | Admin Audit Console | Give admins and auditors a searchable console for user actions, workflow events, permission changes, and integration activity. This supports security and compliance reviews. | Partially Implemented | 2026-05-12-audit-api-contract-foundation-design.md | 2026-05-12-audit-api-contract-foundation.md |  | Searchable audit APIs exist; a dedicated admin or auditor console surface is not evident. |
| P3-27 | Observability Dashboard | Expose job failures, OCR queue health, AI call failures, integration sync status, webhook delivery status, and API errors. Operators need visibility into automation reliability. | Partially Implemented | 2026-05-15-local-demo-system-readiness-design.md | 2026-05-15-local-demo-system-readiness-implementation.md |  | `/system` exposes readiness and environment status, but not the full enterprise dashboard described here. |
| P3-28 | Feature Flags | Control rollout by tenant, role, environment, and feature. Feature flags help introduce enterprise capabilities without disrupting all tenants. | Not Implemented |  |  |  |  |
| P3-29 | Billing and Plan Management | If Cognify is sold as SaaS tiers, support plan limits, usage tracking, invoices, and account billing state. This can wait until product-market packaging is clearer. | Not Implemented |  |  |  |  |
| P3-30 | Usage Analytics | Track feature adoption, workflow completion, user activity, and tenant health. Product analytics guide roadmap prioritization and customer success. | Not Implemented |  |  |  |  |
| P3-31 | Data Export and Portability | Allow tenants to export their procurement records, attachments metadata, audit events, and reports. Enterprise customers expect data portability. | Not Implemented |  |  |  |  |
| P3-32 | Sandbox Tenant | Provide a tenant mode for demos, onboarding, training, or safe policy testing. Sandbox data should be clearly separated from production procurement records. | Not Implemented |  |  |  |  |

## P4 - Optimization, Intelligence, and Marketplace Expansion

These features deepen strategic procurement value after the core platform, governance, and enterprise foundations are working.

| Feature Number | Feature Name | Feature Description | Feature Status | Design Spec | Implementation Plan | PR Number | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| P4-01 | Strategic Sourcing Workspace | Support sourcing events beyond simple RFQs, including multi-round bidding, weighted evaluations, negotiation stages, and award scenarios. This moves Cognify toward strategic procurement programs. | Not Implemented |  |  |  |  |
| P4-02 | Multi-Round RFQ and Negotiation | Allow buyers to request revised bids, track negotiation rounds, compare changes, and preserve vendor communication history. This is useful for high-value or competitive sourcing events. | Not Implemented |  |  |  |  |
| P4-03 | Reverse Auction Support | Enable time-boxed competitive bidding where vendors can improve offers under controlled rules. This should be introduced carefully because it has strong policy and vendor experience implications. | Not Implemented |  |  |  |  |
| P4-04 | Scenario Modeling | Let buyers model award scenarios such as lowest cost, fastest delivery, split award, preferred vendor, or lowest risk. Scenario comparison helps explain complex procurement decisions. | Not Implemented |  |  |  |  |
| P4-05 | Split Award | Support awarding different line items or quantities to multiple vendors. Split awards are common when no single vendor is best for every item. | Not Implemented |  |  |  |  |
| P4-06 | Budget Forecasting | Forecast future spend based on requisition pipeline, projects, renewals, historical patterns, and planned purchases. This supports finance planning. | Not Implemented |  |  |  |  |
| P4-07 | Demand Aggregation | Identify similar upcoming or active requisitions that could be combined for better pricing. Aggregation can create savings by increasing buying leverage. | Not Implemented |  |  |  |  |
| P4-08 | Price Benchmarking | Compare quoted prices against historical purchases, internal benchmarks, or external market data. Benchmarking helps detect overpricing and negotiation opportunities. | Not Implemented |  |  |  |  |
| P4-09 | Vendor Recommendation Engine | Suggest suitable vendors based on category, location, historical performance, compliance status, capacity, and risk. Recommendations should remain explainable and configurable. | Not Implemented |  |  |  |  |
| P4-10 | Category Strategy Insights | Show category-level trends, supplier concentration, savings opportunities, compliance gaps, and renewal risks. This supports category managers rather than only transaction processors. | Not Implemented |  |  |  |  |
| P4-11 | Supplier Diversity Tracking | Track diversity attributes and spend with diverse suppliers where applicable. This supports corporate procurement goals and reporting obligations. | Not Implemented |  |  |  |  |
| P4-12 | ESG and Sustainability Scoring | Capture sustainability data, certifications, emissions attributes, and ESG risk signals. ESG should influence vendor evaluation when tenants configure it as a procurement priority. | Not Implemented |  |  |  |  |
| P4-13 | Local Content and Regional Preference Rules | Support procurement policies that prefer local suppliers or require regional compliance. This matters for public sector, regulated, and multinational procurement. | Not Implemented |  |  |  |  |
| P4-14 | Contract Repository | Manage awarded contracts, amendments, terms, renewal dates, owners, and linked procurement history. A contract repository connects sourcing decisions to ongoing supplier obligations. | Not Implemented |  |  |  |  |
| P4-15 | Contract Clause Extraction | Use OCR and AI to extract payment terms, renewal clauses, termination rights, service levels, warranties, and liability terms. Extracted clauses should be reviewed before use. | Not Implemented |  |  |  |  |
| P4-16 | Contract Obligation Tracking | Track obligations such as insurance renewals, reporting requirements, service reviews, and milestone deliverables. Procurement value continues after award. | Not Implemented |  |  |  |  |
| P4-17 | Supplier Scorecards | Create periodic scorecards combining delivery, quality, responsiveness, savings, risk, compliance, and stakeholder feedback. Scorecards support supplier performance management. | Not Implemented |  |  |  |  |
| P4-18 | Vendor Collaboration Threads | Provide controlled communication channels with vendors for RFQ questions, clarifications, document requests, and negotiation updates. This reduces dependency on email. | Not Implemented |  |  |  |  |
| P4-19 | Vendor Self-Service Profile | Allow vendors to maintain profile data, contacts, documents, categories, certifications, and banking placeholders where appropriate. Tenant review should be required for sensitive changes. | Not Implemented |  |  |  |  |
| P4-20 | Vendor Onboarding Workflow | Route new vendor requests through compliance, finance, risk, and procurement review. This ensures vendor records are approved before use in awards. | Not Implemented |  |  |  |  |
| P4-21 | Vendor Prequalification | Screen vendors before RFQ invitation based on category, compliance, documents, performance, location, and risk. Prequalification reduces wasted sourcing effort. | Not Implemented |  |  |  |  |
| P4-22 | Procurement Knowledge Base | Maintain procurement policies, buying guides, category playbooks, template guidance, and FAQ content. The AI assistant can later use this content as trusted context. | Not Implemented |  |  |  |  |
| P4-23 | Guided Buying Experience | Help requesters choose the correct buying path based on what they need, amount, urgency, category, and policy. Guided buying reduces incomplete and non-compliant requests. | Not Implemented |  |  |  |  |
| P4-24 | Conversational Request Creation | Allow users to draft requisitions through a guided AI conversation that asks for missing details and generates structured fields. The final requisition should still be reviewed before submission. | Not Implemented |  |  |  |  |
| P4-25 | Natural Language Reporting | Let users ask questions such as "Which vendors have the highest cycle time this quarter?" and receive permission-aware answers with charts and links. This makes procurement analytics more accessible. | Not Implemented |  |  |  |  |
| P4-26 | Semantic Evidence Search | Search documents and records by meaning, not only keywords. Users could find prior justifications, similar awards, or vendor exceptions even when wording differs. | Not Implemented |  |  |  |  |
| P4-27 | Cross-Record Relationship Graph | Visualize relationships between users, vendors, requisitions, projects, quotations, approvals, exceptions, and awards. This helps detect concentration, conflicts, and unusual patterns. | Not Implemented |  |  |  |  |
| P4-28 | Predictive Approval Bottlenecks | Predict which requisitions are likely to be delayed based on approver history, amount, category, and missing evidence. Buyers can intervene before SLA breaches happen. | Not Implemented |  |  |  |  |
| P4-29 | Smart Reminder Scheduling | Send reminders at the best time based on urgency, role, workload, and past response patterns. The goal is to reduce noise while improving completion rates. | Not Implemented |  |  |  |  |
| P4-30 | Procurement Health Score | Summarize tenant procurement maturity using compliance rate, cycle time, savings capture, risk exposure, evidence completeness, and vendor performance. This can support customer success and executive reviews. | Not Implemented |  |  |  |  |
| P4-31 | Benchmarking Across Tenants | Provide anonymized benchmark insights such as cycle time, savings rates, or policy exception frequency. This requires careful privacy controls and should come after strong data governance. | Not Implemented |  |  |  |  |
| P4-32 | Marketplace or Vendor Discovery | Offer a curated supplier discovery experience for categories where Cognify can provide vendor suggestions. This is a major product expansion and should follow strong vendor data quality. | Not Implemented |  |  |  |  |
| P4-33 | Mobile Approval Experience | Optimize approval, comment, notification, and quick review workflows for mobile. Procurement approvers often need to respond while away from their desk. | Not Implemented |  |  |  |  |
| P4-34 | Offline Review Packs | Generate offline review packs for restricted environments or executive review. Any offline export should include watermarking, access controls, and audit logging. | Not Implemented |  |  |  |  |

## Implementation Grouping Model

The feature list above is a product capability inventory. It should not be interpreted as one development runbook cycle per heading. Cognify should implement the roadmap through grouped vertical slices that follow `docs/05-runbooks/feature-development.md` once per coherent workflow slice.

### Capability Ownership Rules

Each roadmap capability should have one primary frontend feature group and one primary backend domain, even when implementation touches multiple areas. This keeps ownership clear while allowing workflow slices to span the full product.

| Rule | Guidance |
| --- | --- |
| Primary web feature group | Choose the `apps/web/features/*` area that owns the user's main workflow surface. |
| Primary API domain | Choose the `apps/api/Domains/*` area that owns the durable business state transition. |
| Supporting domains | Use supporting domains for side effects, read models, related records, evidence, metrics, AI, and audit. |
| Cross-cutting infrastructure | Put tenancy, auth, audit, notifications, observability, and framework integration under `apps/api/app/*`, not inside product domains unless the behavior is domain-specific. |
| Shared packages | Use `packages/ui`, `packages/types`, and `packages/schemas` only for stable reusable contracts or primitives, not Cognify business workflows. |

### Capability To Code Ownership Map

| Capability area | Primary web group | Primary API domain | Common supporting areas |
| --- | --- | --- | --- |
| Requisition lifecycle | `apps/web/features/requisitions` | `apps/api/Domains/Requisition` | `Approval`, `EvidenceVault`, `Metric`, `app/Audit`, `app/Tenancy` |
| Approval workflow | `apps/web/features/approvals` | `apps/api/Domains/Approval` | `Requisition`, `Metric`, `app/Audit`, future notification infrastructure |
| Buyer intake and sourcing | `apps/web/features/sourcing` or `apps/web/features/quotations` | `apps/api/Domains/Quotation` | `Requisition`, `Vendor`, `EvidenceVault`, `Approval` |
| Vendor management | `apps/web/features/vendors` | `apps/api/Domains/Vendor` | `EvidenceVault`, `Metric`, `Ai`, `app/Audit` |
| Quotation intake and comparison | `apps/web/features/quotations` | `apps/api/Domains/Quotation` | `Vendor`, `EvidenceVault`, `Ai`, `Metric`, `Approval` |
| Award decision | `apps/web/features/awards` or `apps/web/features/quotations` until awards becomes large enough | `apps/api/Domains/Quotation` initially, later `Award` if split out | `Requisition`, `Approval`, `EvidenceVault`, `Metric`, `app/Audit` |
| Evidence vault and attachments | `apps/web/features/evidence-vault` | `apps/api/Domains/EvidenceVault` | `Requisition`, `Quotation`, `Vendor`, `Ai`, `app/Audit` |
| AI extraction, summaries, and risk explanation | `apps/web/features/ai` for shared surfaces, embedded components inside owning workflow features | `apps/api/Domains/Ai` | `EvidenceVault`, `Requisition`, `Quotation`, `Vendor`, `Metric` |
| Reporting and analytics | `apps/web/features/reporting` | `apps/api/Domains/Reporting` and `apps/api/Domains/Metric` | Read models/events from all workflow domains |
| Project workspace | `apps/web/features/projects` | `apps/api/Domains/Project` | `Requisition`, `Approval`, `Quotation`, `Metric` |
| Tenant administration, roles, and settings | `apps/web/features/admin` or `apps/web/features/settings` | `apps/api/app/Auth` and `apps/api/app/Tenancy` | All domains through policies and membership checks |
| Enterprise integrations | `apps/web/features/integrations` | Integration-specific services under `apps/api/app/*` until a business domain emerges | `Vendor`, `Quotation`, `EvidenceVault`, `Reporting`, `app/Audit` |

Implementation note: do not create all listed web feature groups immediately. Create a group when there is a real route, workflow, hook, mock, or test to place there.

### Runbook Scope

The nine-phase feature-development runbook applies to an implementation slice, not every roadmap heading.

| Planning unit | Purpose | Uses full runbook? | Example |
| --- | --- | --- | --- |
| Product capability | Names a possible product behavior. | No. | "Approval SLA tracking" |
| Epic | Groups related capabilities around a workflow outcome. | Partially, for planning and dependency tracking. | "Approval Baseline" |
| Implementation slice | A thin, end-to-end workflow increment. | Yes. | "Submitted requisition creates approval task and approver can approve or reject" |
| Task | A small engineering step inside a slice. | No. | "Add `ApprovalTask` model" |

This keeps the runbook useful without making the roadmap unbuildable. A slice should still be small enough to review, test, and merge safely.

### Recommended Epics And Slices

| Epic | Candidate capabilities to group | First implementation slice | Depends on | Parallelization notes |
| --- | --- | --- | --- | --- |
| Requisition Foundation | List/detail hardening, comments, mentions, change requests, cancellation, templates, line item suggestions | Requisition comments and change requests with activity timeline | Existing requisition draft/submission | Mostly sequential until the requisition workspace is stable |
| Approval Baseline | Routing rules, approval tasks, sequential approvals, approve/reject, request changes, SLA basics, policy preview | Submitted requisition creates approval task; approver can approve, reject, or request changes | Requisition submission and role baseline | Can run in parallel with Vendor Foundation after requisition contracts stabilize |
| Vendor Foundation | Vendor records, contacts, preferred/restricted status, supplier documents, performance placeholders | Vendor list/detail plus tenant-scoped vendor create/update | Tenant, roles, table/form foundation | Good parallel candidate because it has limited dependency on approvals |
| Buyer Intake And RFQ | Buyer intake queue, RFQ creation, vendor invitation, procurement calendar dates | Buyer converts submitted requisition into RFQ and invites vendors | Requisition submission, Vendor Foundation for real invitations | Can start with mocked vendors, but real invitation should wait for Vendor Foundation |
| Quotation Intake | Quotation upload, manual entry, versioning, vendor portal baseline | Buyer uploads or manually records quotation against RFQ | RFQ creation, file attachment baseline | Can parallelize upload/manual-entry UI and backend storage if OpenAPI is agreed first |
| Quotation Comparison And Award | Normalization, comparison table, scoring matrix, recommendation, award approval, PO handoff | Compare normalized quotation line items and record recommendation rationale | Quotation Intake, Vendor Foundation | Should wait for stable quotation schema |
| Evidence Vault Baseline | Attachments, classification, preview, annotation, evidence links, audit pack foundation | Attach files to requisitions/quotations and show evidence timeline | File attachment baseline, requisition or quotation records | Can run parallel with Approval Baseline if storage contract is stable |
| Policy And Governance | Policy rules, exceptions, conflict declarations, preferred vendor controls, required evidence | Enforce minimum quote count and threshold warning for award/requisition | Approval, RFQ, quotation, evidence records | Should follow enough workflow data to avoid speculative rules |
| AI And OCR Assistance | OCR extraction, review queue, confidence, AI summaries, AI comparison narrative, risk explanation | OCR extraction suggestion from uploaded quotation with human review | Evidence Vault, Quotation Intake, AI provider/fallback | Can split into OCR pipeline and UI review once evidence contracts are stable |
| Reporting And Metrics | Spend dashboard, cycle time, savings, compliance, executive summary, saved views | Requisition and approval cycle-time dashboard from existing events | Audit/activity events, Approval Baseline | Best after workflows emit consistent events |
| Enterprise Administration | Org structure, approval matrix admin, policy admin, user provisioning, SSO, SCIM, retention, audit console | Admin-managed approval threshold matrix | Approval Baseline, role/permission model | Some identity work can run separately, but policy admin depends on actual policies |
| Integrations | ERP export/integration, accounting sync, email intake, webhooks, public API, imports | Approved award export as CSV/JSON | Award decision and vendor master data | External integrations should wait until internal workflow state is stable |
| Strategic Procurement | Strategic sourcing, multi-round RFQ, scenario modeling, split awards, benchmarking, scorecards | Multi-round RFQ revision workflow | RFQ, quotation comparison, award decision | Later-stage work; can parallelize by subdomain after core sourcing matures |

### Dependency Lanes

Use these lanes to decide whether two implementation slices can safely run in parallel:

| Lane | Examples | Parallelization guidance |
| --- | --- | --- |
| Core workflow lane | Requisition, approval, RFQ, quotation, award | Usually sequential because each state transition feeds the next. |
| Master data lane | Vendors, categories, departments, cost centers, projects | Can often run in parallel once tenancy and permissions are stable. |
| Evidence and file lane | Attachments, evidence vault, OCR inputs, audit packs | Can run in parallel with core workflow after storage and ownership rules are decided. |
| Intelligence lane | AI summaries, extraction, risk scoring, recommendations | Should wait for stable source records, but pipeline and UI review can split after contracts are agreed. |
| Analytics lane | Metrics, reports, dashboards, saved views | Should follow real events and state transitions; early read-only dashboards can run in parallel with mature workflows. |
| Enterprise/admin lane | SSO, SCIM, policy admin, retention, integrations | Identity/admin work can run separately; workflow-specific admin should wait for the workflow it configures. |

Parallel work is safe when slices have different write surfaces, stable API contracts, and no unresolved ownership conflict. If two slices need to mutate the same OpenAPI resource, database table, or workflow state machine, sequence them or agree the contract first.

### Suggested Epic Sequence

The recommended sequence after the current requisition draft/submission work is:

1. Requisition Foundation.
2. Approval Baseline.
3. Vendor Foundation.
4. Buyer Intake And RFQ.
5. Quotation Intake.
6. Quotation Comparison And Award.
7. Evidence Vault Baseline.
8. Policy And Governance.
9. Reporting And Metrics.
10. AI And OCR Assistance.
11. Enterprise Administration.
12. Integrations.
13. Strategic Procurement.

Vendor Foundation and Evidence Vault Baseline are the earliest good candidates for parallel work, provided their contracts are kept separate from the active approval workflow changes.

## Cross-Cutting Product Principles

### Build Workflow-First

Each feature should move a real procurement record through a clear state change or decision. Avoid dashboards that only summarize data before the underlying workflow exists.

### Preserve Auditability

Every important action should answer who did what, when, why, on which record, and with which evidence. Auditability should be designed into the domain model and UX from the start.

### Keep AI Assistive and Explainable

AI should summarize, extract, suggest, and explain, while users retain control over procurement decisions. AI outputs should cite evidence or structured fields wherever possible.

### Prefer Thin End-to-End Slices

Implement small but complete workflows across backend, API contract, generated client, frontend UI, tests, and documentation. This keeps Cognify demonstrable and prevents disconnected backend or frontend-only work.

### Make Policy Configurable Before Making It Complex

Hard-coded policy can help early prototypes, but enterprise procurement needs configurable thresholds, categories, exceptions, and routing. Add configuration at the point where multiple customers or tenants would diverge.

### Optimize for Repeated Use

Cognify should feel like a dense, fast operational system. Prioritize clear tables, work queues, keyboard actions, saved views, inline validation, and contextual panels over decorative layouts.

## Suggested Near-Term Sequence

The next several implementation slices after requisition draft submission should be:

1. Requisition list and detail workspace hardening.
2. Requisition comments, activity timeline, and change request workflow.
3. Approval routing baseline with approver work queue.
4. Buyer intake review and RFQ creation from a requisition.
5. Vendor records and vendor invitation to RFQ.
6. Quotation upload and manual quotation entry.
7. Quotation normalization and comparison table.
8. Award recommendation with decision rationale.
9. Evidence vault baseline attached to requisitions, quotations, and awards.
10. Policy rule baseline for quote count, thresholds, and required evidence.
