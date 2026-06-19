import assert from "node:assert/strict";
import test from "node:test";

import { buildDevResetPlan, localPortDefaults } from "./dev-reset-lib.mjs";

test("buildDevResetPlan uses fixed ports and startup order", () => {
  assert.deepEqual(localPortDefaults, {
    api: 8890,
    apiHost: "127.0.0.1",
    db: 5433,
    minioApi: 9002,
    minioConsole: 9003,
    redis: 6381,
    web: 8880,
    webHost: "127.0.0.1",
  });

  const plan = buildDevResetPlan();

  assert.deepEqual(
    plan.steps.map((step) => step.id),
    ["services", "migrate", "api", "web"],
  );
  assert.equal(plan.steps[0].command, "pnpm");
  assert.deepEqual(plan.steps[0].args, ["dev:services"]);
  assert.equal(plan.steps[1].cwd, "apps/api");
  assert.deepEqual(plan.steps[1].args, ["artisan", "migrate:fresh", "--seed"]);
  assert.equal(plan.steps[2].command, "composer");
  assert.deepEqual(plan.steps[2].args, ["run", "dev"]);
  assert.equal(plan.steps[3].command, "pnpm");
  assert.deepEqual(plan.steps[3].args, ["--filter", "@cognify/web", "dev"]);
});
