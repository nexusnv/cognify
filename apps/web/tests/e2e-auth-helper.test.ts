import { readFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";

const dirname = path.dirname(fileURLToPath(import.meta.url));
const authHelperSource = readFileSync(path.join(dirname, "e2e/helpers/auth.ts"), "utf8");

describe("Playwright auth helper", () => {
  it("locates the password field by accessible label", () => {
    expect(authHelperSource).toContain('getByLabel("Password", { exact: true })');
    expect(authHelperSource).not.toContain('getByRole("textbox", { name: "Password" })');
  });
});
