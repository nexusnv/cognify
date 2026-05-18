import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const apiDockerfile = readFileSync("infrastructure/docker/api.Dockerfile", "utf8");
const previewCompose = readFileSync("infrastructure/docker/docker-compose.pr-preview.yml", "utf8");

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
