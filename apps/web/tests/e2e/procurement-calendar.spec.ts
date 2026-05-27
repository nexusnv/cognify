import { expect, test } from "@playwright/test";
import { signIn } from "./helpers/auth";

test("buyer opens procurement calendar and follows an RFQ event", async ({ page }) => {
  await signIn(page);

  await page.goto("/calendar");
  await expect(page.getByRole("heading", { name: "Calendar" })).toBeVisible();

  await page.getByLabel("Source type").selectOption("rfqDeadline");
  await page.getByRole("button", { name: /RFQ/i }).first().click();
  await page.getByRole("link", { name: "Open source" }).click();

  await expect(page).toHaveURL(/\/(sourcing\/rfqs|quotations\/comparisons)\//);
});
