import { http, HttpResponse } from "msw";
import { vendorPickerFixtures } from "./vendor-fixtures";

let vendors = structuredClone(vendorPickerFixtures);

export function resetVendorMockState() {
  vendors = structuredClone(vendorPickerFixtures);
}

export const vendorHandlers = [
  http.get("/api/vendors", ({ request }) => {
    const url = new URL(request.url);
    const status = url.searchParams.get("status");
    const category = url.searchParams.get("category")?.toLowerCase();

    const data = vendors.filter((vendor) => {
      const matchesStatus = !status || vendor.status === status;
      const matchesCategory = !category || vendor.category?.toLowerCase() === category;
      return matchesStatus && matchesCategory;
    });

    return HttpResponse.json({ data });
  }),
];
