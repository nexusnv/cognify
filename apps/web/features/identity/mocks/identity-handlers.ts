import { http, HttpResponse } from "msw";
import type { CurrentUserContext } from "../types/identity-view-model";
import { requesterIdentity } from "./identity-fixtures";

let currentIdentity: CurrentUserContext = requesterIdentity;
let authenticated = true;

export function resetIdentityMockState() {
  currentIdentity = requesterIdentity;
  authenticated = true;
}

export const identityHandlers = [
  http.post("/api/auth/login", async () => {
    authenticated = true;
    return new HttpResponse(null, { status: 204 });
  }),
  http.post("/api/auth/logout", () => {
    authenticated = false;
    return new HttpResponse(null, { status: 204 });
  }),
  http.post("/api/auth/forgot-password", () => new HttpResponse(null, { status: 204 })),
  http.get("/api/me", () => {
    if (!authenticated) {
      return HttpResponse.json({ message: "Unauthenticated." }, { status: 401 });
    }
    return HttpResponse.json({ data: currentIdentity });
  }),
  http.patch("/api/me/profile", async ({ request }) => {
    const body = (await request.json()) as Partial<CurrentUserContext["user"]>;
    currentIdentity = {
      ...currentIdentity,
      user: { ...currentIdentity.user, ...body },
    };
    return HttpResponse.json({ data: currentIdentity });
  }),
  http.post("/api/tenants/current", async ({ request }) => {
    const body = (await request.json()) as { tenantId?: string };
    const membership = currentIdentity.tenants.find((tenant) => tenant.id === body.tenantId);
    if (!membership) {
      return HttpResponse.json({ message: "Tenant membership is required." }, { status: 403 });
    }
    currentIdentity = {
      ...currentIdentity,
      activeTenant: { id: membership.id, name: membership.name },
      activeRole: membership.role,
    };
    return HttpResponse.json({ data: currentIdentity });
  }),
];
