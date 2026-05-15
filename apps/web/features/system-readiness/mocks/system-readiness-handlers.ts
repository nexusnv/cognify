import { http, HttpResponse } from "msw";
import { healthySystemStatus } from "./system-readiness-fixtures";

export const systemReadinessHandlers = [
  http.get("/api/system/status", () => HttpResponse.json(healthySystemStatus)),
];
