import { expect, test } from "@playwright/test";

test("buyer opens procurement calendar and follows an RFQ event", async ({ page }) => {
  await signIn(page);

  await page.goto("/calendar");
  await expect(page.getByRole("heading", { name: "Calendar" })).toBeVisible();

  await page.getByLabel("Source type").selectOption("rfqDeadline");
  await page.getByRole("button", { name: /RFQ/i }).first().click();
  await page.getByRole("link", { name: "Open source" }).click();

  await expect(page).toHaveURL(/\/(sourcing\/rfqs|quotations\/comparisons)\//);
});

async function signIn(page: import("@playwright/test").Page) {
  await page.goto("/login");

  await expect(page.getByRole("heading", { name: "Sign in to Cognify" })).toBeVisible();
  await page.getByLabel("Email").fill("test@example.com");
  await page.getByLabel("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();

  await expect(page.getByText("Signed in")).toBeVisible();
}
