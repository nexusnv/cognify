import { readFileSync, readdirSync, statSync } from "node:fs";
import { join, relative } from "node:path";

const root = process.cwd();
const scanRoots = ["apps/web/app", "apps/web/components", "apps/web/features"];
const allowedCustomUiRoot = "apps/web/components/ui";
const allowedCustomUiGroups = new Set(["headers", "graph", "scorecard", "procurement-table", "workflow-state"]);
const rawInteractivePattern = /<(button|input|select|textarea)\b(?![^>]*data-allow-raw-control)/g;
const rawTablePattern = /<(table|thead|tbody|tr|th|td)\b(?![^>]*data-allow-raw-table)/g;
const roleDialogPattern = /role=["']dialog["']/g;
const customUiPattern = /^apps\/web\/components\/(?!ui\/|providers\/|shell\/shell-route-config|shell\/shell-types|shell\/shell-utils)/;
const packageUiForbiddenPattern = /Cognify|procurement|requisition|approval|rfq|quotation|tenant|vendor/i;
const packageUiRoots = ["packages/ui/src/components", "packages/ui/src/hooks", "packages/ui/src/lib"];
const packageUiAllowedFiles = new Set([
  "packages/ui/src/components/accordion.tsx",
  "packages/ui/src/components/alert.tsx",
  "packages/ui/src/components/alert-dialog.tsx",
  "packages/ui/src/components/avatar.tsx",
  "packages/ui/src/components/badge.tsx",
  "packages/ui/src/components/breadcrumb.tsx",
  "packages/ui/src/components/button.tsx",
  "packages/ui/src/components/button-group.tsx",
  "packages/ui/src/components/calendar.tsx",
  "packages/ui/src/components/card.tsx",
  "packages/ui/src/components/chart.tsx",
  "packages/ui/src/components/checkbox.tsx",
  "packages/ui/src/components/combobox.tsx",
  "packages/ui/src/components/command.tsx",
  "packages/ui/src/components/dialog.tsx",
  "packages/ui/src/components/dropdown-menu.tsx",
  "packages/ui/src/components/empty.tsx",
  "packages/ui/src/components/field.tsx",
  "packages/ui/src/components/form.tsx",
  "packages/ui/src/components/input.tsx",
  "packages/ui/src/components/input-group.tsx",
  "packages/ui/src/components/kbd.tsx",
  "packages/ui/src/components/label.tsx",
  "packages/ui/src/components/native-select.tsx",
  "packages/ui/src/components/popover.tsx",
  "packages/ui/src/components/progress.tsx",
  "packages/ui/src/components/radio-group.tsx",
  "packages/ui/src/components/scroll-area.tsx",
  "packages/ui/src/components/select.tsx",
  "packages/ui/src/components/separator.tsx",
  "packages/ui/src/components/sheet.tsx",
  "packages/ui/src/components/sidebar.tsx",
  "packages/ui/src/components/skeleton.tsx",
  "packages/ui/src/components/sonner.tsx",
  "packages/ui/src/components/spinner.tsx",
  "packages/ui/src/components/switch.tsx",
  "packages/ui/src/components/table.tsx",
  "packages/ui/src/components/tabs.tsx",
  "packages/ui/src/components/textarea.tsx",
  "packages/ui/src/components/toggle.tsx",
  "packages/ui/src/components/toggle-group.tsx",
  "packages/ui/src/components/tooltip.tsx",
  "packages/ui/src/hooks/use-mobile.ts",
  "packages/ui/src/lib/utils.ts",
]);

const failures = [];

function walk(dir) {
  let entries = [];
  try {
    entries = readdirSync(join(root, dir));
  } catch {
    return [];
  }

  return entries.flatMap((entry) => {
    const path = join(dir, entry);
    const absolute = join(root, path);
    const stat = statSync(absolute);
    if (stat.isDirectory()) return walk(path);
    if (!/\.(tsx|ts)$/.test(path)) return [];
    if (path.endsWith(".test.ts") || path.endsWith(".test.tsx")) return [];
    return [path];
  });
}

function assertNoMatches(file, pattern, message) {
  const source = readFileSync(join(root, file), "utf8");
  const matches = source.match(pattern);
  if (matches) {
    failures.push(`${file}: ${message} (${matches.length} match${matches.length === 1 ? "" : "es"})`);
  }
}

for (const file of scanRoots.flatMap(walk)) {
  assertNoMatches(file, rawInteractivePattern, "raw interactive control; use shadcn primitive");
  assertNoMatches(file, rawTablePattern, "raw table markup; use shadcn Table primitives");
  assertNoMatches(file, roleDialogPattern, "hand-rolled dialog role; use shadcn Dialog/AlertDialog/Sheet");

  if (customUiPattern.test(file) && !file.includes("/providers/")) {
    failures.push(`${file}: custom reusable UI outside ${allowedCustomUiRoot}`);
  }

  if (file.startsWith(`${allowedCustomUiRoot}/`) && file !== `${allowedCustomUiRoot}/README.md`) {
    const group = file.slice(`${allowedCustomUiRoot}/`.length).split("/")[0];
    const source = readFileSync(join(root, file), "utf8");

    if (!allowedCustomUiGroups.has(group)) {
      failures.push(`${file}: custom UI exception outside allowed groups`);
    }

    if (!source.split("\n").slice(0, 5).join("\n").includes("shadcn-factory-exception:")) {
      failures.push(`${file}: missing top-level shadcn-factory-exception comment`);
    }
  }
}

for (const file of packageUiRoots.flatMap(walk)) {
  if (!packageUiAllowedFiles.has(file)) {
    failures.push(`${file}: unexpected file in generated primitive package`);
  }

  assertNoMatches(file, packageUiForbiddenPattern, "Cognify-specific language in generated primitive package");
}

if (failures.length > 0) {
  console.error("Shadcn factory UI audit failed:");
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log("Shadcn factory UI audit passed.");
