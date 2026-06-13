---
name: visual-inspection
description: Run Cognify visual inspection for substantial UI changes before completion. Use when a feature adds new screens, changes existing workflow UI, touches layout/navigation/components, or the user asks for screenshot-based UI/UX review, visual QA, or browser inspection evidence.
---

# Visual Inspection

## Overview

Inspect Cognify UI changes against the real app, not only MSW or unit tests. Capture evidence across desktop and a relevant smaller non-mobile viewport, critique the workflow as an enterprise procurement work surface, and fix reliable findings before completion.

## Workflow

1. Determine whether visual inspection is required.
   - Required for new screens, substantial UI/UX changes to existing screens, navigation/layout changes, or user-requested visual QA.
   - Skip only for small non-visual changes, and state the skip reason.

2. Start the real local app.
   - Prefer `pnpm dev:reset` when a seeded API-backed session is needed.
   - Otherwise start the relevant API/web services already established by the repo.
   - Do not rely only on MSW for completion evidence.

3. Use seeded or realistic demo data.
   - Exercise the actual workflow path a reviewer would use.
   - For authenticated Cognify flows, use the seeded local credentials or existing local auth helpers from the repo/runbooks.

4. Capture screenshots.
   - Use `playwright-cli`, Playwright tests/scripts, or available browser tooling.
   - Capture at least one desktop viewport and one relevant smaller non-mobile viewport.
   - Do not inspect mobile screens for Cognify; mobile screen support has been dropped.
   - Save screenshots under an ignored artifact path such as `artifacts/visual-inspection/<feature>/`.

5. Critique the screenshots and workflow.
   - Verify the workflow actually works.
   - Check whether the UI is the right approach for the Cognify feature.
   - Check component placement, density, labels, states, and interactions against the user's procurement job.
   - Check usability, accessibility basics, keyboard awareness, and responsive behavior.
   - Check that the design matches Cognify's enterprise procurement expectations.
   - Check that it avoids decorative or marketing-style UI where an operational work surface is needed.
   - Use `ui-ux-pro-max` when available for constructive screenshot critique.

6. Resolve reliable findings.
   - Fix issues that are reproducible, clearly user-impacting, and in scope for the branch.
   - Re-run the relevant screenshot/check after fixing.
   - If a finding is uncertain, document it as a residual risk instead of changing code speculatively.

7. Report evidence.
   - List inspected URLs, viewport sizes, screenshot paths, and any fixes made.
   - Include this evidence in the PR description when a PR is opened.
