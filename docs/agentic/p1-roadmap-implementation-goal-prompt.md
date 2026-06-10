# P1 Roadmap Implementation Goal

Goal: Implement all remaining P1 Cognify features listed in `docs/01-product/feature-roadmap.md`, one feature at a time, following a strict branch → design → implementation → review → PR → merge loop. You are to follow the instructions in this file religously. Apart from the spec design decision below, you do not own your own decision in regards to skipping or modifying the instructions given in here. If in any case you can't proceed, ask for the user's confirmation.

## Repository context

- Cognify is a multi-tenant, enterprise procure-to-pay SaaS.
- Follow `AGENTS.md`, `ARCHITECTURE.md`, `docs/agentic/AGENTIC_CODING_GUIDELINES.md`, `docs/05-runbooks/feature-development.md`, and `docs/04-engineering/standards/*`; follow the repo boundaries in the roadmap.
- Cognify-specific app workflows live in `apps/web`.
- Laravel business domains live in `apps/api/Domains/*`.
- Cross-cutting Laravel infrastructure lives in `apps/api/app/*`.
- `packages/ui` is for reusable shadcn/Radix primitives only.
- API contract changes must update OpenAPI, regenerate `@cognify/api-client`, and consume generated schemas/endpoints instead of duplicating response types.

## Target scope

- Source of truth: `docs/01-product/feature-roadmap.md`.
- Work only on P1 — Core Procure-To-Pay Lifecycle.
- At the start of the goal and before each feature, re-read the roadmap and inspect the live code to determine which P1 features are not implemented or only partially implemented.
- As of this prompt, the expected remaining P1 range is P1-37 through P1-54:
  - P1-37 Purchase Order Review and Approval (completed)
  - P1-38 Purchase Order Issue to Supplier (completed)
  - P1-39 Purchase Order Change Orders
  - P1-40 Receiving and Goods Receipt
  - P1-41 Delivery and Fulfillment Tracking
  - P1-42 Supplier Invoice Capture
  - P1-43 Invoice Review Workspace
  - P1-44 Two-Way and Three-Way Matching
  - P1-45 Invoice Exception Workflow
  - P1-46 Invoice Approval
  - P1-47 Payment Readiness and AP Handoff
  - P1-48 Payment Status Tracking
  - P1-49 Credit Memo and Invoice Adjustment
  - P1-50 Budget Commitment and Encumbrance
  - P1-51 Vendor Master Baseline for P2P
  - P1-52 Tax, Currency, and Payment Terms Baseline
  - P1-53 Procure-To-Pay Record Graph
  - P1-54 P2P Operational Queues
- If a feature is already fully implemented, prove that with code/test/doc evidence, update the roadmap status if needed, and move to the next feature.
- If adjacent P1 features are tightly coupled, prefer the smallest coherent vertical slice that leaves the product usable. Do not merge multiple roadmap rows into one branch unless the design spec explicitly justifies why they are inseparable.

## Feature loop

For each remaining P1 feature, execute this exact sequence.

### 1. Sync main and create a feature branch

- Checkout `goal-feature-staging`.
- Pull the latest remote `goal-feature-staging`.
- Create a new branch named `goal-feature/<feature-number-lowercase-and-short-slug>` from `goal-feature-staging` branch.
- Example: `goal-feature/p1-37-po-review-approval`.
- Never carry unrelated uncommitted changes into the feature branch. If the worktree is dirty, inspect it and preserve user work.

### 2. Brainstorm and design

- Use the `superpowers:brainstorming` skill.
- Read current code, specs, plans, roadmap rows, and adjacent implemented workflows.
- Produce a design spec for this feature under `docs/superpowers/specs/YYYY-MM-DD-<feature-slug>-design.md`.
- The agent owns the design decision. Choose what is best for Cognify as a real-world enterprise procure-to-pay SaaS, not the fastest demo.
- Design for realistic procurement operations: tenant isolation, permissions, auditability, operational queues, failure recovery, state transitions, role-specific UX, and integration-ready data.
- Follow existing app/domain patterns unless the codebase clearly needs a focused improvement to support the feature.
- Keep the slice thin but complete end-to-end.

### 3. Write an implementation plan

- Use the `superpowers:writing-plans` skill after the design is accepted by the agent's quality bar. Write a high-fidelity, high-quality implementation plan. Ground your plan to the project architecture (ARCHITECTURE.md), and the project `docs/05-runbooks/feature-development.md` runbook.
- Save the plan under `docs/superpowers/plans/YYYY-MM-DD-<feature-slug>.md`.
- The plan must include:
  - workflow map
  - API contract changes
  - backend domain changes
  - web workflow and UI changes
  - OpenAPI / client generation steps
  - tests and verification commands
  - migration / seed / demo data needs
  - visual inspection needs, if screens are added or substantially changed
  - PR completion checklist

### 4. Execute the plan sub-agently

- Use the `superpowers:subagent-driven-development` skill. Make a commit after each implementation-plan task is completed.
- Use subagents where tasks are independent and can be safely parallelized. If you have a problem spawning a sub-agent, stop your work and ask the user for clarification or ways to move forward.
- Keep all implementation work on the current `goal-feature/*` branch.
- Use TDD or regression-first edits when fixing findings or changing existing behavior.
- Respect repo boundaries:
  - `apps/web` for Cognify workflows and screens
  - `apps/api/Domains/*` for durable business logic
  - `apps/api/app/*` for cross-cutting Laravel concerns
  - `packages/api-client` for generated contract access
  - `packages/ui` only for reusable primitives without business meaning
- Do not import mock fixtures directly into production UI components.
- Ensure tenant checks, authorization, audit events, and validation are covered for every workflow transition.

### 5. Verify locally

- Run narrow checks for touched files first.
- Run broader checks when shared contracts, generated clients, package boundaries, providers, middleware, migrations, or shared config change.
- For API contract changes, run API generation and contract checks.
- Use real route middleware tests for Sanctum/session-sensitive behavior.
- Do not claim completion until verification commands have been run and their outputs are reviewed.
- Spawn a subagent to run a strict spec-compliance review, comparing the implementation against the implementation-plan specification document it relates to. Verify and address the review results before moving on.
- Spawn a subagent to run a strict code-quality review. This review ensures that the implementation plan has been carried out fully and that the resulting code is well structured and follows the project `ARCHITECTURE.md` and the feature development runbook (`docs/05-runbooks/feature-development.md`). Address the review results before moving on.

### 6. Visual inspection gate

- If the feature adds new screens or makes substantial UI/UX changes to existing screens, visual inspection is mandatory before completion.
- Start the local app with real API / local services, not only MSW.
- Use seeded or realistic demo data.
- Capture screenshots with `playwright-cli` skill or running Playwright on the apps/web target, or the available browser tooling across at least desktop and a relevant smaller viewport.
- Critique the screenshots against:
  - whether the workflow actually works
  - whether this is the best UI/UX approach for this Cognify feature
  - whether component placement, density, labels, states, and interactions serve the user's job
  - whether it is user-friendly, accessible, keyboard-aware, and responsive
  - whether it matches Cognify's enterprise procurement aspirations
  - whether it avoids decorative or marketing-style UI where an operational work surface is needed
- Use the `ui-ux-pro-max` skill for constructive screenshot critique when available. If the current coding model does not have the visual capability, resort to descriptive critique using available skills.
- Address relevant critique feedback before moving on.

### 7. Independent CodeRabbit review

- Use CodeRabbit AI through the available CodeRabbit / Coderabbit MCP or skill.
- Wait for CodeRabbit to return all review comments before acting.
- Do not start more than one CodeRabbit review within a 30-minute window.
- Always acknowledge the user if you are waiting for the timed window to complete or to open a new window.
- Do not run more than 1 CodeRabbit review cycle for a single feature branch.
- Apply necessary fixes from CodeRabbit comments.
- Re-run relevant verification after fixes.

### 8. Commit, push, and open PR

- Commit all feature changes with a clear message.
- Push the `goal-feature/*` branch.
- Open a GitHub PR in ready-for-review state (not draft).
- The PR description must begin with this statement: "IMPORTANT. This PR comprises the changes made to fully implement [INSERT_IMPLEMENTATION_PLAN_RELATIVE_PATH] in accordance with the design specification [INSERT_DESIGN_SPEC_FILE_RELATIVE_PATH]."
- Include the tests run, visual inspection evidence (if applicable), and CodeRabbit review/fix summary in the PR description.
- Include the running PR instance URL (when available), for example: `https://pr-[INSERT PR NUMBER]-preview.nexusnv.net`, and list all accessible/testable URLs to guide reviewers.

### 9. Wait for human / PR review

- Wait 30–45 minutes for review comments after opening the PR. If the diff is large or checks are still running, wait longer.
- Retrieve all unresolved PR review comments and inline threads.
- Apply necessary fixes where the comments are valid and relevant.
- If a comment is technically questionable, use the `superpowers:receiving-code-review` skill before changing code.
- Commit and push fixes.
- Wait for the next review cycle and repeat until there are no unresolved review comments or a blocker requires user input.

### 10. Merge and reset to main

- When CI is green, CodeRabbit review is addressed, human/PR comments are resolved, and no blockers remain, merge the PR into `goal-feature-staging` using the repo's expected merge strategy.
- When merge is successful, delete the current `goal-feature/*` branch on remote.
- Checkout `goal-feature-staging` and run `git fetch --prune` to remove all deleted branch.
- Pull the latest remote `goal-feature-staging`.
- Confirm `goal-feature-staging` includes the merged feature.
- Update `docs/01-product/feature-roadmap.md` for the completed feature if the feature status, spec path, plan path, PR number, or notes changed.
- If the roadmap update was not included in the PR, make a follow-up docs commit/PR before starting the next feature.

### 11. Continue to the next feature

- Start the next feature only after `goal-feature-staging` is updated with the previously merged PR.
- Repeat the loop from branch creation.

## Definition of done for the overall goal

- Every P1 feature in `docs/01-product/feature-roadmap.md` is either:
  - fully implemented, verified, reviewed, merged, and marked "Fully Implemented" in the roadmap, or
  - explicitly documented as blocked with the blocker, evidence, and next required human/external action.
- `goal-feature-staging` is checked out and up to date with remote.
- No feature branch is left with unpushed completed work.
- The final response lists completed P1 features, PRs, verification evidence, remaining blockers (if any), and any prerequisites that were missing during execution.

## Operational constraints

- Preserve user work. Never reset, checkout, or delete unrelated changes without explicit approval.
- Do not skip tests because they are slow. If a test cannot run because of environment issues, document the exact failure and use the narrowest alternative verification available.
- Do not rely on `actingAs`-only tests for Sanctum/session behavior where real route middleware matters.
- Do not update client-side tenant state until the server validates the tenant.
- Do not duplicate generated API response types in app code.
- Do not use mock-only verification for completed production workflows.
- Do not run destructive git commands unless explicitly approved.
- run `pnpm dev:reset` to reset postgres database and reseed the tables and run the api and web server.
- Complete web test suit will take quite lengthy to completely run all the test, so whenever posible, opt for narrowed test.
