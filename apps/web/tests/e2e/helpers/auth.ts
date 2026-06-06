import { expect, type Page } from "@playwright/test";

export async function signIn(page: Page, email = "test@example.com") {
  await page.context().clearCookies();
  await page.goto("/login");

  await expect(page.getByRole("heading", { name: "Sign in to your procurement workspace" })).toBeVisible();
  await page.evaluate(() => {
    window.localStorage.clear();
    window.sessionStorage.clear();
  });
  await page.getByLabel("Email").fill(email);
  await page.getByRole("textbox", { name: "Password" }).fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();

  const workspaceHeading = page.getByRole("heading", { name: "Choose workspace" });

  try {
    await expect(workspaceHeading).toBeVisible({ timeout: 5000 });
    await page.getByRole("radio", { name: "Acme Procurement" }).click();
    await expect(page.getByRole("button", { name: "Continue" })).toBeEnabled();
    await page.getByRole("button", { name: "Continue" }).click();
  } catch {
    // Single-workspace users continue directly to the dashboard.
  }

  await expect(page.getByRole("heading", { name: "Dashboard" })).toBeVisible();
}
