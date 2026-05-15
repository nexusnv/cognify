import assert from "node:assert/strict";
import test from "node:test";

import { buildComposeInvocation, buildComposeUpArgs, composeFile } from "./docker-compose-lib.mjs";

test("buildComposeInvocation supports docker compose and docker-compose", () => {
  assert.equal(composeFile, "infrastructure/docker/docker-compose.yml");

  assert.deepEqual(buildComposeInvocation(false, ["up", "-d", "--wait"]), {
    command: "docker",
    args: ["compose", "-f", composeFile, "up", "-d", "--wait"],
  });

  assert.deepEqual(buildComposeInvocation(true, ["down"]), {
    command: "docker-compose",
    args: ["-f", composeFile, "down"],
  });
});

test("buildComposeUpArgs starts only long-lived services", () => {
  assert.deepEqual(buildComposeUpArgs(), ["up", "-d", "--wait", "postgres", "redis", "minio"]);
});
