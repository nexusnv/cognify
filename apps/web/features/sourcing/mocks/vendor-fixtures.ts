import type { VendorPickerItem } from "@cognify/api-client/schemas";

export const vendorPickerFixtures = [
  {
    id: "1",
    name: "Northwind Traders",
    category: "IT Hardware",
    status: "active",
    riskRating: "low",
    defaultContact: {
      name: "Nina Northwind",
      email: "nina@northwind.test",
    },
  },
  {
    id: "2",
    name: "Atlas Workplace Supply",
    category: "Office Supplies",
    status: "active",
    riskRating: "medium",
    defaultContact: {
      name: "Alicia Atlas",
      email: "alicia@atlas.test",
    },
  },
  {
    id: "3",
    name: "Keystone Tech Services",
    category: "Managed Services",
    status: "active",
    riskRating: "low",
    defaultContact: {
      name: "Kiera Keystone",
      email: "kiera@keystone.test",
    },
  },
  {
    id: "4",
    name: "Redwood Office Systems",
    category: "Office Supplies",
    status: "inactive",
    riskRating: "high",
    defaultContact: {
      name: "Rhea Redwood",
      email: "rhea@redwood.test",
    },
  },
] satisfies VendorPickerItem[];
