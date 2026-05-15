import assert from "node:assert/strict";
import test from "node:test";

import { bucketName, serviceDefinitions } from "./dev-services-lib.mjs";

test("serviceDefinitions use Cognify-specific non-default host ports", () => {
  assert.equal(bucketName, "cognify-dev");
  assert.equal(serviceDefinitions.postgres.hostPort, 5433);
  assert.equal(serviceDefinitions.redis.hostPort, 6381);
  assert.equal(serviceDefinitions.minio.apiPort, 9002);
  assert.equal(serviceDefinitions.minio.consolePort, 9003);
});
