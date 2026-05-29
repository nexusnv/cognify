import { readdirSync, readFileSync, statSync } from "node:fs";
import { join } from "node:path";

const roots = ["apps/web/app", "apps/web/components", "apps/web/features"];
const ignoredSegments = new Set(["mocks", "types", "schemas"]);

function collectFiles(directory) {
  const entries = readdirSync(directory);
  const files = [];

  for (const entry of entries) {
    const path = join(directory, entry);
    const stat = statSync(path);

    if (stat.isDirectory()) {
      if (!ignoredSegments.has(entry)) {
        files.push(...collectFiles(path));
      }
      continue;
    }

    if (
      (path.endsWith(".tsx") || path.endsWith(".ts")) &&
      !path.endsWith(".test.ts") &&
      !path.endsWith(".test.tsx")
    ) {
      files.push(path);
    }
  }

  return files;
}

const files = roots.flatMap((root) => collectFiles(root));

const checks = [
  { pattern: /<button[\s>]/, label: "raw <button>" },
  { pattern: /<input[\s>]/, label: "raw <input>" },
  { pattern: /<select[\s>]/, label: "raw <select>" },
  { pattern: /<textarea[\s>]/, label: "raw <textarea>" },
  { pattern: /<table[\s>]/, label: "raw <table>" },
  { pattern: /role="dialog"/, label: "hand-rolled dialog" },
  { pattern: /className="[^"]*rounded-md border[^"]*p-[3456]/, label: "custom bordered panel" },
];

const allowed = new Map([
  ["apps/web/components/data-table/data-table.tsx", ["raw <table>"]],
  ["apps/web/features/procurement-calendar/components/procurement-calendar-month-view.tsx", []],
  ["apps/web/features/procurement-calendar/components/procurement-calendar-week-view.tsx", []],
]);

const failures = [];

for (const file of files) {
  const source = readFileSync(file, "utf8");
  const fileAllowed = allowed.get(file) ?? [];

  for (const check of checks) {
    if (fileAllowed.includes(check.label)) {
      continue;
    }

    if (check.pattern.test(source)) {
      failures.push(`${file}: ${check.label}`);
    }
  }
}

if (failures.length > 0) {
  console.error("Shadcn-first audit failed:");
  for (const failure of failures) {
    console.error(`- ${failure}`);
  }
  process.exit(1);
}

console.log(`Shadcn-first audit passed for ${files.length} files.`);
