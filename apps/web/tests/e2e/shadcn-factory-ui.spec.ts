import { expect, test } from "@playwright/test";
import { signIn } from "./helpers/auth";

async function useAuthenticatedSmokeSession(page: Parameters<typeof signIn>[0]) {
  // Keep this smoke focused on rendered shadcn UI; full session middleware is covered by API tests.
  await page.route("**/sanctum/csrf-cookie", async (route) => {
    await route.fulfill({ status: 204 });
  });

  await page.route("**/api/auth/login", async (route) => {
    await route.fulfill({ status: 204 });
  });

  await page.route("**/api/me", async (route) => {
    await route.fulfill({
      contentType: "application/json",
      body: JSON.stringify({
        data: {
          user: {
            id: "1",
            name: "Test User",
            email: "test@example.com",
            avatarUrl: null,
            timezone: "Asia/Kuala_Lumpur",
            locale: "en",
            theme: "system",
            notificationPreferences: {
              approvalAssigned: true,
              approvalDecided: true,
              requisitionCommentMention: true,
              systemReadinessIssue: true,
            },
          },
          tenants: [{ id: "1", name: "Acme Procurement", role: "requester" }],
          activeTenant: { id: "1", name: "Acme Procurement" },
          activeRole: "requester",
          permissions: {
            canCreateRequisition: true,
            canViewSubmittedRequisitions: false,
            canUpdateOwnDraftRequisition: true,
            canSubmitOwnDraftRequisition: true,
            canAccessAdmin: false,
            canManageSourcingIntake: false,
            canReviewQuotationNormalization: false,
          },
        },
      }),
    });
  });
}

test.describe("shadcn factory UI smoke", () => {
  test("app shell exposes visible theme toggle and d shortcut", async ({ page }) => {
    await useAuthenticatedSmokeSession(page);
    await signIn(page);
    await page.goto("/dashboard");

    const themeToggle = page.getByRole("button", { name: /switch to .* mode/i });
    await expect(themeToggle).toBeVisible();

    const html = page.locator("html");
    const before = await html.evaluate((node) => node.classList.contains("dark"));

    await page.keyboard.press("d");
    await expect
      .poll(async () => html.evaluate((node) => node.classList.contains("dark")))
      .toBe(!before);

    await page.goto("/account");
    await page.getByRole("textbox").first().focus();
    const after = await html.evaluate((node) => node.classList.contains("dark"));
    await page.keyboard.press("d");
    await expect.poll(async () => html.evaluate((node) => node.classList.contains("dark"))).toBe(after);
  });

  test("representative dense workflow routes render without horizontal overflow", async ({ page }) => {
    await useAuthenticatedSmokeSession(page);
    await signIn(page);

    const routes = [
      "/dashboard",
      "/requisitions",
      "/projects",
      "/approvals",
      "/sourcing/intake",
      "/quotations/normalizations",
      "/calendar",
      "/system",
    ];

    for (const route of routes) {
      await page.goto(route);
      await expect(page.locator("body")).toBeVisible();
      const hasOverflow = await page.evaluate(
        () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
      );
      expect(hasOverflow, `${route} should not horizontally overflow`).toBe(false);
    }
  });
});
