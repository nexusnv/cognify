import { expect, test } from "@playwright/test";
import { signIn } from "./helpers/auth";

test("buyer opens procurement calendar and follows a source event", async ({ page }) => {
  await signIn(page);

  await page.goto("/calendar");
  await expect(page.getByRole("heading", { name: "Calendar" })).toBeVisible();

  await page.getByRole("textbox", { name: "From" }).fill("2026-06-01");
  await page.getByRole("textbox", { name: "To" }).fill("2026-06-30");
  await page.getByRole("region", { name: "Calendar month view" }).getByRole("button").first().click();
  await page.getByRole("link", { name: "Open source" }).click();

  await expect(page).toHaveURL(/\/(requisitions|sourcing\/rfqs|quotations\/comparisons)\//);
});
