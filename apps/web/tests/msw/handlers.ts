import { http, HttpResponse } from "msw";
import { requisitionsHandlers } from "../../features/requisitions/mocks/requisitions-handlers";

export const handlers = [
  http.get("/api/health", () => {
    return HttpResponse.json({
      status: "ok",
      service: "cognify-api",
    });
  }),
  ...requisitionsHandlers,
];
