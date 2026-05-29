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

    await page.getByRole("button", { name: "Fill empty fields from IT equipment" }).click();
    await expect(page.getByRole("dialog", { name: "Apply template?" })).toBeHidden();

    await expect(page.getByLabel("Business justification")).toHaveValue(
      "Provision or replace equipment required for business operations.",
    );
    await expect(page.getByLabel("Item name 1")).toHaveValue("Laptop");

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

    const lineItemName = page.getByLabel("Item name 1");
    await lineItemName.fill("Mon");
    await expect(page.getByRole("button", { name: "Monitor" })).toBeVisible();
    await page.getByRole("button", { name: "Monitor" }).click();

    await expect(lineItemName).toHaveValue("Monitor");
    await expect(page.getByLabel("Unit 1")).toHaveValue("each");
    await expect(page.getByLabel("Estimated unit price 1")).toHaveValue("700");
    await expect(page.getByLabel("Currency 1")).toHaveValue("MYR");

    const saveSuggestionResponsePromise = page.waitForResponse(
      (response) =>
        response.request().method() === "PATCH" &&
        new URL(response.url()).pathname === `/api/requisitions/${requisitionId}` &&
        response.status() === 200,
    );
    await page.getByRole("button", { name: "Save draft" }).click();
    const saveSuggestionResponse = await saveSuggestionResponsePromise;
    const saveSuggestionPayload = (await saveSuggestionResponse.json()) as createRequisitionResponse201["data"];
    const patchPayload = await page.evaluate(
      async ({ lockVersion, requisitionId }) => {
        const xsrfToken = document.cookie
          .split(";")
          .map((part) => part.trim())
          .find((part) => part.startsWith("XSRF-TOKEN="))
          ?.slice("XSRF-TOKEN=".length);
        const tenantId = window.localStorage.getItem("cognify.activeTenantId");
        const headers = new Headers({
          Accept: "application/json",
          "Content-Type": "application/json",
        });

        if (xsrfToken) headers.set("X-XSRF-TOKEN", decodeURIComponent(xsrfToken));
        if (tenantId) headers.set("X-Tenant-Id", tenantId);

        const response = await fetch(`/api/requisitions/${requisitionId}`, {
          method: "PATCH",
          credentials: "include",
          headers,
          body: JSON.stringify({
            title: "Field laptop refresh",
            lockVersion,
          }),
        });
        const text = await response.text();

        if (!response.ok) throw new Error(text);
        return JSON.parse(text);
      },
      { lockVersion: saveSuggestionPayload.data.lockVersion, requisitionId },
    ) as createRequisitionResponse201["data"];
    createPayload.data.lockVersion = patchPayload.data.lockVersion;

    await page.getByLabel("Title").fill("Field laptop refresh v2");
    await page.getByRole("button", { name: "Save draft" }).click();

    await expect(page.getByText("This draft changed elsewhere.")).toBeVisible();
    await expect(page.getByLabel("Title")).toHaveValue("Field laptop refresh v2");
  });
});
