import { expect, test } from "@playwright/test";
import type { createRequisitionResponse201 } from "@cognify/api-client";
import { signIn } from "./helpers/auth";

test.describe("requisition authoring critical path", () => {
  test("logs in, saves a draft, applies a template, selects a suggestion, and shows a stale-save conflict", async ({
    page,
  }) => {
    test.slow();

    await signIn(page);
    await page.goto("/requisitions/new");

    await expect(page.getByRole("heading", { name: "New requisition" })).toBeVisible();

    await page.getByLabel("Title").fill("Field laptop refresh");

    const createResponsePromise = page.waitForResponse(
      (response) =>
        response.request().method() === "POST" &&
        new URL(response.url()).pathname === "/api/requisitions" &&
        response.status() === 201,
    );
    await page.getByRole("button", { name: "Save draft" }).click();

    const createResponse = await createResponsePromise;
    const createPayload = (await createResponse.json()) as createRequisitionResponse201["data"];
    const requisitionId = createPayload.data.id;

    await expect(page.getByText("Saved")).toBeVisible();

    await page.getByRole("button", { name: "Fill empty fields from IT equipment" }).click();
    const templateDialog = page.getByRole("dialog", { name: "Apply template?" });
    await expect(templateDialog).toBeVisible();
    await expect(templateDialog).toContainText("IT equipment");
    await templateDialog.getByRole("button", { name: "Apply template" }).click();

    await expect(page.getByLabel("Business justification")).toHaveValue(
      "Provision or replace equipment required for business operations.",
    );
    await expect(page.getByLabel("Item name 1")).toHaveValue("Laptop");

    const lineItemName = page.getByLabel("Item name 1");
    await lineItemName.fill("Mon");
    await expect(page.getByRole("button", { name: "Monitor each · MYR 700" })).toBeVisible();
    await page.getByRole("button", { name: "Monitor each · MYR 700" }).click();

    await expect(lineItemName).toHaveValue("Monitor");
    await expect(page.getByLabel("Unit 1")).toHaveValue("each");
    await expect(page.getByLabel("Estimated unit price 1")).toHaveValue("700");
    await expect(page.getByLabel("Currency 1")).toHaveValue("MYR");

    const patchResponse = await page.request.patch(new URL(`/api/requisitions/${requisitionId}`, page.url()).toString(), {
      data: {
        title: "Field laptop refresh",
        lockVersion: createPayload.data.lockVersion + 1,
      },
    });
    expect(patchResponse.ok()).toBeTruthy();
    const patchPayload = (await patchResponse.json()) as createRequisitionResponse201["data"];
    createPayload.data.lockVersion = patchPayload.data.lockVersion;

    await page.getByLabel("Title").fill("Field laptop refresh v2");
    await page.getByRole("button", { name: "Save draft" }).click();

    await expect(page.getByRole("alert")).toContainText("This draft changed elsewhere.");
    await expect(page.getByLabel("Title")).toHaveValue("Field laptop refresh v2");
  });
});
