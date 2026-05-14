import { defineConfig } from "orval";

export default defineConfig({
  cognify: {
    input: "../../apps/api/storage/openapi/openapi.json",
    output: {
      mode: "split",
      target: "src/generated/endpoints.ts",
      schemas: "src/generated/schemas",
      client: "fetch",
      clean: true,
      prettier: true,
      override: {
        mutator: {
          path: "src/client.ts",
          name: "cognifyFetch",
        },
        formData: {
          path: "src/form-data.ts",
          name: "buildFormData",
        },
      },
    },
  },
});
