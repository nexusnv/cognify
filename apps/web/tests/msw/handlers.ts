import { http, HttpResponse } from "msw";
import { identityHandlers } from "../../features/identity/mocks/identity-handlers";
import { requisitionsHandlers } from "../../features/requisitions/mocks/requisitions-handlers";

export const handlers = [
  http.get("/api/health", () => {
    return HttpResponse.json({
      status: "ok",
      service: "cognify-api",
    });
  }),
  ...requisitionsHandlers,
  ...identityHandlers,
];
