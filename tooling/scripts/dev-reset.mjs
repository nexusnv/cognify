import { spawn } from "node:child_process";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

import { buildDevResetPlan, localPortDefaults } from "./dev-reset-lib.mjs";

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), "../..");

function resolveCwd(cwd = ".") {
  return resolve(repoRoot, cwd);
}

function formatCommand(step) {
  return [step.command, ...step.args].join(" ");
}

function runStep(step) {
  return new Promise((resolveStep, rejectStep) => {
    const child = spawn(step.command, step.args, {
      cwd: resolveCwd(step.cwd),
      env: process.env,
      stdio: "inherit",
    });

    child.on("error", rejectStep);
    child.on("exit", (code, signal) => {
      if (code === 0) {
        resolveStep();
        return;
      }

      rejectStep(
        new Error(`${step.id} exited with code ${code ?? "null"} and signal ${signal ?? "none"}`),
      );
    });
  });
}

function startLongRunningStep(step) {
  return spawn(step.command, step.args, {
    cwd: resolveCwd(step.cwd),
    env: process.env,
    stdio: "inherit",
  });
}

async function main() {
  const plan = buildDevResetPlan();
  const [servicesStep, migrateStep, apiStep, webStep] = plan.steps;

  console.log("Cognify local dev reset");
  console.log(`  web:   http://127.0.0.1:${localPortDefaults.web}`);
  console.log(`  api:   http://127.0.0.1:${localPortDefaults.api}`);
  console.log(`  db:    127.0.0.1:${localPortDefaults.db}`);
  console.log(`  redis: 127.0.0.1:${localPortDefaults.redis}`);
  console.log(`  minio: http://127.0.0.1:${localPortDefaults.minioApi}`);

  for (const step of [servicesStep, migrateStep]) {
    console.log(`\n==> ${step.id}: ${formatCommand(step)}`);
    await runStep(step);
  }

  console.log(`\n==> api: ${formatCommand(apiStep)}`);
  const apiProcess = startLongRunningStep(apiStep);

  console.log(`\n==> web: ${formatCommand(webStep)}`);
  const webProcess = startLongRunningStep(webStep);

  const children = [apiProcess, webProcess];
  let shuttingDown = false;

  const stopChildren = (signal = "SIGTERM") => {
    if (shuttingDown) {
      return;
    }

    shuttingDown = true;

    for (const child of children) {
      if (!child.killed) {
        child.kill(signal);
      }
    }
  };

  process.on("SIGINT", () => {
    stopChildren("SIGINT");
  });

  process.on("SIGTERM", () => {
    stopChildren("SIGTERM");
  });

  const exitCode = await new Promise((resolveExit, rejectExit) => {
    let settled = false;

    for (const [index, child] of children.entries()) {
      child.on("error", (error) => {
        if (settled) {
          return;
        }

        settled = true;
        rejectExit(error);
      });

      child.on("exit", (code, signal) => {
        if (settled) {
          return;
        }

        settled = true;

        if (code === 0 || signal === "SIGINT" || signal === "SIGTERM") {
          resolveExit(0);
          return;
        }

        const stepId = index === 0 ? apiStep.id : webStep.id;
        rejectExit(new Error(`${stepId} exited with code ${code ?? "null"} and signal ${signal ?? "none"}`));
      });
    }
  }).finally(() => {
    stopChildren();
  });

  process.exit(exitCode);
}

main().catch((error) => {
  console.error(`dev:reset failed: ${error.message}`);
  process.exit(1);
});
