import { spawn } from "node:child_process";
import { setTimeout as delay } from "node:timers/promises";

import { buildComposeInvocation, buildComposeUpArgs } from "./docker-compose-lib.mjs";
import { bucketName, serviceDefinitions } from "./dev-services-lib.mjs";

function run(command, args, options = {}) {
  return new Promise((resolve, reject) => {
    const child = spawn(command, args, {
      env: process.env,
      stdio: options.stdio ?? "inherit",
    });

    let stdout = "";
    let stderr = "";

    if (child.stdout) {
      child.stdout.on("data", (chunk) => {
        stdout += chunk.toString();
      });
    }

    if (child.stderr) {
      child.stderr.on("data", (chunk) => {
        stderr += chunk.toString();
      });
    }

    child.on("error", reject);
    child.on("exit", (code, signal) => {
      if (code === 0) {
        resolve({ stdout, stderr });
        return;
      }

      reject(new Error(`${command} exited with code ${code ?? "null"} and signal ${signal ?? "none"}`));
    });
  });
}

async function supportsDockerComposePlugin() {
  try {
    await run("docker", ["compose", "version"], { stdio: "ignore" });
    return true;
  } catch {
    return false;
  }
}

async function supportsLegacyComposeBinary() {
  try {
    await run("docker-compose", ["version"], { stdio: "ignore" });
    return true;
  } catch {
    return false;
  }
}

async function runCompose(mode) {
  const useLegacyBinary = !(await supportsDockerComposePlugin());
  const composeArgs = mode === "up" ? buildComposeUpArgs() : ["down"];
  const invocation = buildComposeInvocation(useLegacyBinary, composeArgs);
  await run(invocation.command, invocation.args);

  if (mode === "up") {
    await initBucket();
  }
}

async function containerExists(name) {
  try {
    await run("docker", ["container", "inspect", name], { stdio: "ignore" });
    return true;
  } catch {
    return false;
  }
}

async function removeContainer(name) {
  if (!(await containerExists(name))) {
    return;
  }

  await run("docker", ["rm", "-f", name]);
}

async function inspectContainerHealth(name) {
  const { stdout } = await run(
    "docker",
    ["inspect", "--format", "{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}", name],
    { stdio: "pipe" },
  );

  return stdout.trim();
}

async function waitForHealthy(name, timeoutMs = 120_000) {
  const deadline = Date.now() + timeoutMs;

  while (Date.now() < deadline) {
    const status = await inspectContainerHealth(name);

    if (status === "healthy" || status === "running") {
      return;
    }

    if (status === "unhealthy" || status === "exited" || status === "dead") {
      throw new Error(`${name} entered unexpected status ${status}`);
    }

    await delay(1_000);
  }

  throw new Error(`${name} did not become healthy within ${timeoutMs}ms`);
}

async function initBucket() {
  const { minio } = serviceDefinitions;

  await run("docker", [
    "run",
    "--rm",
    "--network",
    "host",
    "-e",
    `MC_HOST_local=http://minioadmin:minioadmin@127.0.0.1:${minio.apiPort}`,
    "minio/mc:latest",
    "mb",
    "--ignore-existing",
    `local/${bucketName}`,
  ]);
}

async function runDockerFallbackUp() {
  const { postgres, redis, minio } = serviceDefinitions;

  await removeContainer(postgres.name);
  await removeContainer(redis.name);
  await removeContainer(minio.name);

  await run("docker", [
    "run",
    "-d",
    "--name",
    postgres.name,
    "-p",
    `${postgres.hostPort}:${postgres.containerPort}`,
    "-e",
    "POSTGRES_USER=postgres",
    "-e",
    "POSTGRES_PASSWORD=secret",
    "-e",
    "POSTGRES_DB=cognify_dev",
    "-v",
    postgres.volume,
    "--health-cmd",
    "pg_isready -U postgres -d cognify_dev",
    "--health-interval",
    "10s",
    "--health-timeout",
    "5s",
    "--health-retries",
    "5",
    postgres.image,
  ]);

  await run("docker", [
    "run",
    "-d",
    "--name",
    redis.name,
    "-p",
    `${redis.hostPort}:${redis.containerPort}`,
    "-v",
    redis.volume,
    "--health-cmd",
    "redis-cli ping",
    "--health-interval",
    "10s",
    "--health-timeout",
    "5s",
    "--health-retries",
    "5",
    redis.image,
    "redis-server",
    "--appendonly",
    "yes",
  ]);

  await run("docker", [
    "run",
    "-d",
    "--name",
    minio.name,
    "-p",
    `${minio.apiPort}:9000`,
    "-p",
    `${minio.consolePort}:9001`,
    "-e",
    "MINIO_ROOT_USER=minioadmin",
    "-e",
    "MINIO_ROOT_PASSWORD=minioadmin",
    "-v",
    minio.volume,
    "--health-cmd",
    "curl -f http://localhost:9000/minio/health/live",
    "--health-interval",
    "10s",
    "--health-timeout",
    "5s",
    "--health-retries",
    "5",
    minio.image,
    "server",
    "/data",
    "--console-address",
    ":9001",
  ]);

  await waitForHealthy(postgres.name);
  await waitForHealthy(redis.name);
  await waitForHealthy(minio.name);

  await initBucket();
}

async function runDockerFallbackDown() {
  const { postgres, redis, minio } = serviceDefinitions;

  await removeContainer(minio.name);
  await removeContainer(redis.name);
  await removeContainer(postgres.name);
}

async function main() {
  const mode = process.argv[2];

  if (mode !== "up" && mode !== "down") {
    console.error("Usage: node tooling/scripts/dev-services.mjs <up|down>");
    process.exit(1);
  }

  if (await supportsDockerComposePlugin()) {
    await runCompose(mode);
    return;
  }

  if (await supportsLegacyComposeBinary()) {
    await runCompose(mode);
    return;
  }

  console.log("docker compose is unavailable, using direct docker service bootstrap");

  if (mode === "up") {
    await runDockerFallbackUp();
    return;
  }

  await runDockerFallbackDown();
}

main().catch((error) => {
  console.error(`dev:services failed: ${error.message}`);
  process.exit(1);
});
