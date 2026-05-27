import { expect, type Page } from "@playwright/test";

export async function signIn(page: Page, email = "test@example.com") {
  await page.goto("/login");

  await expect(page.getByRole("heading", { name: "Sign in to Cognify" })).toBeVisible();
  await page.getByLabel("Email").fill(email);
  await page.getByLabel("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();

  await expect(page.getByText("Signed in")).toBeVisible();
}
