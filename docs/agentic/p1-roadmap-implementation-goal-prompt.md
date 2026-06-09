# P1 Roadmap Implementation Goal Prompt

Use this prompt to start a long-running Codex goal for finishing every remaining P1 feature in `docs/01-product/feature-roadmap.md`.

```text
Goal: Implement all remaining P1 Cognify features from docs/01-product/feature-roadmap.md, one feature at a time, using a strict branch, design, implementation, review, PR, and merge loop.

Repository context:
- Cognify is a multi-tenant enterprise procure-to-pay SaaS.
- Follow AGENTS.md, ARCHITECTURE.md, docs/agentic/AGENTIC_CODING_GUIDELINES.md, docs/05-runbooks/feature-development.md, docs/04-engineering/standards/*, and the repo boundaries in the roadmap.
- Cognify-specific app workflows live in apps/web.
- Laravel business domains live in apps/api/Domains/*.
- Cross-cutting Laravel infrastructure lives in apps/api/app/*.
- packages/ui is for reusable shadcn/Radix primitives only.
- API contract changes must update OpenAPI, regenerate @cognify/api-client, and consume generated schemas/endpoints instead of duplicating response types.

Target scope:
- Source of truth: docs/01-product/feature-roadmap.md.
- Work only on P1 - Core Procure-To-Pay Lifecycle.
- At the start of the goal and before each feature, re-read the roadmap and inspect the live code to determine which P1 features are still not implemented or only partially implemented.
- As of this prompt, the expected remaining P1 range is P1-37 through P1-54:
  - P1-37 Purchase Order Review and Approval
  - P1-38 Purchase Order Issue to Supplier
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

Feature loop:
For each remaining P1 feature, execute this exact sequence.

1. Sync main and create a feature branch
- Checkout main.
- Pull the latest remote main.
- Create a new branch named goal-feature/<feature-number-lowercase-and-short-slug>.
- Example: goal-feature/p1-37-po-review-approval.
- Never carry unrelated uncommitted changes into the feature branch. If the worktree is dirty, inspect it and preserve user work.

2. Brainstorm and design
- Use the superpowers:brainstorming skill.
- Read current code, specs, plans, roadmap rows, and adjacent implemented workflows.
- Produce a design spec for this feature under docs/superpowers/specs/YYYY-MM-DD-<feature-slug>-design.md.
- The agent owns the design decision. Choose what is best for Cognify as a real-world enterprise procure-to-pay SaaS, not the fastest demo.
- Design for realistic procurement operations: tenant isolation, permissions, auditability, operational queues, failure recovery, state transitions, role-specific UX, and integration-ready data.
- Follow existing app/domain patterns unless the codebase clearly needs a focused improvement to support the feature.
- Keep the slice thin but complete end to end.

3. Write an implementation plan
- Use the superpowers:writing-plans skill after the design is accepted by the agent's own quality bar.
- Save the plan under docs/superpowers/plans/YYYY-MM-DD-<feature-slug>.md.
- The plan must include:
  - workflow map
  - API contract changes
  - backend domain changes
  - web workflow and UI changes
  - OpenAPI/client generation steps
  - tests and verification commands
  - migration/seed/demo data needs
  - visual inspection needs, if screens are added or substantially changed
  - PR completion checklist

4. Execute the plan sub-agently
- Use subagents where tasks are independent and can be safely parallelized.
- Keep all implementation work on the current goal-feature/* branch.
- Use TDD or regression-first edits when fixing findings or changing existing behavior.
- Respect repo boundaries:
  - apps/web for Cognify workflows and screens
  - apps/api/Domains/* for durable business logic
  - apps/api/app/* for cross-cutting Laravel concerns
  - packages/api-client for generated contract access
  - packages/ui only for reusable primitives without business meaning
- Do not import mock fixtures directly into production UI components.
- Ensure tenant checks, authorization, audit events, and validation are covered for every workflow transition.

5. Verify locally
- Run narrow checks for touched files first.
- Run broader checks when shared contracts, generated clients, package boundaries, providers, middleware, migrations, or shared config change.
- For API contract changes, run API generation and contract checks.
- Use real route middleware tests for Sanctum/session-sensitive behavior.
- Do not claim completion until verification commands have been run and their outputs are reviewed.

6. Visual inspection gate
- If the feature adds new screens or makes substantial UI/UX changes to existing screens, visual inspection is mandatory before completion.
- Start the local app with real API/local services, not only MSW.
- Use seeded or realistic demo data.
- Capture screenshots with Playwright or the available browser tooling across at least desktop and a relevant smaller viewport.
- Critique the screenshots against:
  - whether the workflow actually works
  - whether this is the best UI/UX approach for this Cognify feature
  - whether component placement, density, labels, states, and interactions serve the user's job
  - whether it is user-friendly, accessible, keyboard-aware, and responsive
  - whether it matches Cognify's enterprise procurement aspirations
  - whether it avoids decorative or marketing-style UI where an operational work surface is needed
- Use the i-ux-pro-max skill for constructive screenshot critique when available.
- Address relevant critique feedback before moving on.

7. Independent CodeRabbit review
- Use CodeRabbit AI through the available CodeRabbit/Coderabbit MCP or skill.
- Wait for CodeRabbit to return all review comments before acting.
- Do not start more than one CodeRabbit review within a 15 minute window.
- Do not run more than 2 CodeRabbit review cycles for a single feature branch.
- Apply necessary fixes from CodeRabbit comments.
- Re-run relevant verification after fixes.

8. Commit, push, and open PR
- Commit all feature changes with a clear message.
- Push the goal-feature/* branch.
- Open a GitHub PR in ready-for-review state, not draft.
- Include the design spec, implementation plan, tests run, visual inspection evidence if applicable, and CodeRabbit review/fix summary in the PR description.

9. Wait for human/PR review
- Wait 10-15 minutes for review comments after opening the PR. If the diff is large or checks are still running, wait longer.
- Retrieve all unresolved PR review comments and inline threads.
- Apply necessary fixes where the comments are valid and relevant.
- If a comment is technically questionable, use the superpowers:receiving-code-review skill before changing code.
- Commit and push fixes.
- Wait for the next review cycle.
- Repeat until there are no new unresolved review comments or until a blocker requires user input.

10. Merge and reset to main
- When CI is green, CodeRabbit review is addressed, human/PR comments are resolved, and no blockers remain, merge the PR into main using the repo's expected merge strategy.
- Checkout main.
- Pull latest remote main.
- Confirm main includes the merged feature.
- Update docs/01-product/feature-roadmap.md for the completed feature if the feature status, spec path, plan path, PR number, or notes changed.
- If the roadmap update was not included in the PR, make a follow-up docs commit/PR before starting the next feature.

11. Continue to the next feature
- Start the next feature only after main is updated with the previous merged PR.
- Repeat the loop from branch creation.

Definition of done for the overall goal:
- Every P1 feature in docs/01-product/feature-roadmap.md is either:
  - fully implemented, verified, reviewed, merged, and marked Fully Implemented in the roadmap, or
  - explicitly documented as blocked with the blocker, evidence, and next required human/external action.
- Main is checked out and up to date with remote.
- No feature branch is left with unpushed completed work.
- The final response lists completed P1 features, PRs, verification evidence, remaining blockers if any, and any prerequisites that were missing during execution.

Operational constraints:
- Preserve user work. Never reset, checkout, or delete unrelated changes without explicit approval.
- Do not skip tests because they are slow. If a test cannot run because of environment issues, document the exact failure and use the narrowest alternative verification available.
- Do not rely on actingAs-only tests for Sanctum/session behavior where real route middleware matters.
- Do not update client-side tenant state until the server validates the tenant.
- Do not duplicate generated API response types in app code.
- Do not use mock-only verification for completed production workflows.
- Do not run destructive git commands unless explicitly approved.
```

## Prerequisites And Dependencies

Prepare these before starting the goal so agents can run the loop without avoidable interruptions.

### Repository And GitHub

- `main` is clean, protected, and pushable through PR merge.
- Remote `origin` is configured.
- The agent has permission to create branches named `goal-feature/*`.
- The agent has permission to push branches, open ready-for-review PRs, read PR review comments, resolve/respond to comments where appropriate, and merge approved PRs.
- GitHub CLI `gh` is authenticated, or the GitHub connector app is installed and authorized for this repository.
- Branch protection, required checks, and merge strategy are known.

### Codex Skills And Apps

- Superpowers plugin installed and available, especially:
  - `superpowers:brainstorming`
  - `superpowers:writing-plans`
  - `superpowers:subagent-driven-development`
  - `superpowers:dispatching-parallel-agents`
  - `superpowers:test-driven-development`
  - `superpowers:systematic-debugging`
  - `superpowers:verification-before-completion`
  - `superpowers:receiving-code-review`
- CodeRabbit plugin/app/MCP installed and authorized for independent reviews.
- GitHub plugin/app installed for PR metadata, review comments, and PR lifecycle operations.
- `i-ux-pro-max` skill installed if visual critique should use that exact skill name. If unavailable, the agent should perform a rigorous screenshot critique manually and note that the skill was unavailable.
- Multi-agent tooling available for subagent execution.

### Local Toolchain

- Node.js and pnpm versions compatible with the repo.
- PHP and Composer versions compatible with `apps/api`.
- Database/client tools required by the Laravel app.
- Docker or the documented local service runner available for `pnpm dev:services`.
- Playwright browser dependencies installed for screenshot and E2E verification.
- Any required image/screenshot tooling available in the agent environment.

### Local Environment

- `.env` files are present for `apps/web`, `apps/api`, and root-level tooling where required.
- Local services can start with:
  - `pnpm dev:services`
  - `pnpm dev`
- Laravel migrations and seeders can run locally.
- Demo data is sufficient to exercise requester, buyer, approver, finance/AP, receiver, admin, and vendor-facing P2P workflows.
- Real API verification is possible against local services; MSW-only verification is not enough for completed workflows.

### Verification Commands

Agents should be able to run the relevant commands from AGENTS.md and the feature runbook, including:

- `pnpm install`
- `pnpm lint`
- `pnpm typecheck`
- `pnpm test`
- `pnpm build`
- `pnpm dev`
- `pnpm dev:services`
- `pnpm dev:services:down`
- `pnpm generate:api`
- `pnpm check:api-contract`
- Laravel API test commands from `docs/05-runbooks/feature-development.md`

### Review And Rate Limits

- CodeRabbit review quota/rate limits are available.
- Enforce at least 15 minutes between CodeRabbit review starts.
- Enforce no more than 2 CodeRabbit review cycles per feature branch.
- Human/PR reviewers know to expect a 10-15 minute review wait window per feature, longer for large diffs.

### Product Inputs

- `docs/01-product/feature-roadmap.md` is accepted as the P1 source of truth.
- Existing specs and plans under `docs/superpowers/specs` and `docs/superpowers/plans` are available for adjacent workflow context.
- The agent is allowed to make product/design decisions based on Cognify's target market: enterprise procurement/procure-to-pay teams needing operational clarity, governance, auditability, and realistic AP/procurement workflows.
