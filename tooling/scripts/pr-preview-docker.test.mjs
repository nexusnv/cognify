import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";
import test from "node:test";

const apiDockerfile = readFileSync("infrastructure/docker/api.Dockerfile", "utf8");
const previewCompose = readFileSync("infrastructure/docker/docker-compose.pr-preview.yml", "utf8");
const mainPreviewWorkflowPath = ".github/workflows/main-preview.yml";
const mainPreviewWorkflow = existsSync(mainPreviewWorkflowPath)
  ? readFileSync(mainPreviewWorkflowPath, "utf8")
  : "";

test("API preview container serves HTTP without artisan serve env injection", () => {
  assert.match(apiDockerfile, /cd public && php -S 0\.0\.0\.0:8890/);
  assert.doesNotMatch(apiDockerfile, /artisan serve/);
});

test("API preview container treats the preview domain as a Sanctum stateful origin", () => {
  assert.match(previewCompose, /SANCTUM_STATEFUL_DOMAINS:.*\$\{PREVIEW_DOMAIN\}/);
});

test("API preview container uses the bundled Redis client", () => {
  assert.match(previewCompose, /REDIS_CLIENT: predis/);
  assert.match(previewCompose, /REDIS_PORT: "6379"/);
});

test("main preview workflow deploys only from updates to main", () => {
  assert.notEqual(mainPreviewWorkflow, "", "main preview workflow should exist");
  assert.match(mainPreviewWorkflow, /name: Main Preview/);
  assert.match(mainPreviewWorkflow, /push:\n\s+branches:\n\s+- main/);
  assert.doesNotMatch(mainPreviewWorkflow, /pull_request:/);
});

test("main preview workflow replaces a single fixed stack", () => {
  assert.match(mainPreviewWorkflow, /group: main-preview/);
  assert.match(mainPreviewWorkflow, /PREVIEW_NAME="main-preview"/);
  assert.match(mainPreviewWorkflow, /PREVIEW_DOMAIN="main-preview\.nexusnv\.net"/);
  assert.match(
    mainPreviewWorkflow,
    /docker compose --project-name "\$PREVIEW_NAME" -f "\$COMPOSE_FILE" down --volumes --remove-orphans \|\| true/,
  );
});
