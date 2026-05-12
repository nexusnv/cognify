#!/usr/bin/env node

import { createHash } from "node:crypto";
import { execFileSync, execSync } from "node:child_process";
import { cpSync, existsSync, mkdtempSync, readdirSync, readFileSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { join, relative, resolve } from "node:path";

const repoRoot = execFileSync("git", ["rev-parse", "--show-toplevel"], {
  encoding: "utf8",
}).trim();

const apiClientDir = resolve(repoRoot, "packages/api-client");
const generatedDir = resolve(apiClientDir, "src/generated");
const openApiPath = resolve(repoRoot, "apps/api/storage/openapi/openapi.json");

if (!existsSync(openApiPath)) {
  console.error(`OpenAPI contract not found: ${relative(repoRoot, openApiPath)}`);
  process.exit(1);
}

const tempDir = mkdtempSync(join(tmpdir(), "cognify-api-contract-"));
const beforeDir = join(tempDir, "generated-before");

try {
  if (existsSync(generatedDir)) {
    cpSync(generatedDir, beforeDir, { recursive: true });
  }

  execSync("pnpm run generate", {
    cwd: apiClientDir,
    shell: true,
    stdio: "inherit",
  });

  const before = snapshotFiles(beforeDir);
  const after = snapshotFiles(generatedDir);
  const changedFiles = diffSnapshots(before, after);

  if (changedFiles.length > 0) {
    console.error("Generated API client output drifted from the OpenAPI contract.");
    console.error("Regenerated files were left in the working tree for review.");
    console.error("");
    console.error(changedFiles.map((file) => `  ${file}`).join("\n"));
    process.exit(1);
  }
} finally {
  rmSync(tempDir, { recursive: true, force: true });
}

function snapshotFiles(dir) {
  if (!existsSync(dir)) {
    return new Map();
  }

  const files = new Map();

  for (const file of listFiles(dir)) {
    files.set(relative(dir, file), hashFile(file));
  }

  return files;
}

function listFiles(dir) {
  return readdirSync(dir, { withFileTypes: true })
    .flatMap((entry) => {
      const entryPath = join(dir, entry.name);

      if (entry.isDirectory()) {
        return listFiles(entryPath);
      }

      return entry.isFile() ? [entryPath] : [];
    })
    .sort();
}

function hashFile(file) {
  return createHash("sha256").update(readFileSync(file)).digest("hex");
}

function diffSnapshots(before, after) {
  const files = new Set([...before.keys(), ...after.keys()]);

  return [...files]
    .filter((file) => before.get(file) !== after.get(file))
    .map((file) => relative(repoRoot, join(generatedDir, file)))
    .sort();
}
