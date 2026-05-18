import { expect, test, type Page } from "@playwright/test";

test.describe("approval orchestration critical path", () => {
  test.describe.configure({ mode: "serial" });

  test("@p1-approval admin publishes policy and approver approves routed requisition", async ({ page }) => {
    test.slow();

    await signIn(page, "admin@example.com");
    await page.goto("/approval-policies/new");
    await expect(page.getByRole("heading", { name: "New approval policy" })).toBeVisible();

    await page.getByLabel("Policy name").fill(`P1 approval policy ${Date.now()}`);
    await page.getByRole("button", { name: "Create policy" }).click();
    await expect(page.getByText("Approval policy draft created")).toBeVisible();
    await page.getByRole("button", { name: "Publish version" }).click();
    await expect(page.getByText("Approval policy version published")).toBeVisible();

    await resetSession(page);
    await signIn(page, "finance@example.com");
    await openApprovalTask(page, "Security audit services");
    await page.getByRole("button", { name: "Approve" }).click();
    await page.getByRole("dialog", { name: "Approve task?" }).getByRole("button", { name: "Confirm approval" }).click();
    await expect(page.getByText("Approval recorded")).toBeVisible();
  });

  test("@p1-approval approver rejects with required reason", async ({ page }) => {
    await signIn(page, "buyer@example.com");
    await openApprovalTask(page, "HQ workplace refresh");

    await page.getByRole("button", { name: "Reject" }).click();
    const dialog = page.getByRole("dialog", { name: "Reject task?" });
    await dialog.getByRole("button", { name: "Confirm rejection" }).click();
    await expect(dialog.getByText("Reason is required.")).toBeVisible();

    await dialog.getByLabel("Reason").fill("Budget threshold requires procurement review.");
    await expect(dialog.getByLabel("Reason")).toHaveValue("Budget threshold requires procurement review.");
  });

  test("@p1-approval approver requests changes with required reason", async ({ page }) => {
    await signIn(page, "buyer@example.com");
    await openApprovalTask(page, "HQ workplace refresh");

    await page.getByRole("button", { name: "Request changes" }).click();
    const dialog = page.getByRole("dialog", { name: "Request changes?" });
    await dialog.getByRole("button", { name: "Confirm request changes" }).click();
    await expect(dialog.getByText("Reason is required.")).toBeVisible();

    await dialog.getByLabel("Reason").fill("Attach the supplier risk note.");
    await dialog.getByLabel("Requested fields").fill("attachments");
    await expect(dialog.getByLabel("Requested fields")).toHaveValue("attachments");
  });

  test("@p1-approval delegate can act on delegated task", async ({ page }) => {
    await signIn(page, "buyer@example.com");
    await openApprovalTask(page, "HQ workplace refresh");

    await expect(page.getByText("Buyer User")).toBeVisible();
    await page.getByRole("button", { name: "Approve" }).click();
    await page.getByRole("dialog", { name: "Approve task?" }).getByRole("button", { name: "Confirm approval" }).click();
    await expect(page.getByText("Approval recorded")).toBeVisible();
  });

  test("@p1-approval unauthorized user cannot act on another approver task", async ({ page }) => {
    await signIn(page, "test@example.com");
    await page.goto("/approvals/tasks/1");

    await expect(page.getByText("Approval task could not be loaded.")).toBeVisible();
    await expect(page.getByRole("button", { name: "Approve" })).toHaveCount(0);
  });
});

async function signIn(page: Page, email: string) {
  await page.goto("/login");

  await expect(page.getByRole("heading", { name: "Sign in to Cognify" })).toBeVisible();
  await page.getByLabel("Email").fill(email);
  await page.getByLabel("Password").fill("password");
  await page.getByRole("button", { name: "Sign in" }).click();

  await expect(page.getByText("Signed in")).toBeVisible();
}

async function resetSession(page: Page) {
  await page.context().clearCookies();
  await page.evaluate(() => {
    window.localStorage.clear();
    window.sessionStorage.clear();
  });
}

async function openApprovalTask(page: Page, requisitionTitle: string) {
  await page.goto("/approvals");
  await expect(page.getByRole("heading", { name: "Approvals" })).toBeVisible();
  await expect(page.getByRole("table", { name: "Approval tasks" })).toBeVisible();

  const row = page.getByRole("row").filter({ hasText: requisitionTitle }).first();
  await expect(row).toBeVisible();
  await row.getByRole("link", { name: "Open" }).click();
  await expect(page.getByRole("heading", { name: requisitionTitle })).toBeVisible();
}
