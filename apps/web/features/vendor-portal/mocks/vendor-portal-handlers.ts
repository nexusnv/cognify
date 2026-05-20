import { http, HttpResponse } from "msw";
import {
  expiredVendorPortalToken,
  unavailableVendorPortalToken,
  validVendorPortalToken,
  vendorPortalRfqInvitationFixture,
} from "./vendor-portal-fixtures";

export const vendorPortalHandlers = [
  http.get("/api/vendor-portal/rfq-invitations/:token", ({ params }) => {
    const token = String(params.token);

    if (token === validVendorPortalToken) {
      return HttpResponse.json({ data: structuredClone(vendorPortalRfqInvitationFixture) });
    }

    if (token === expiredVendorPortalToken) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "This vendor portal link has expired." } },
        { status: 409 },
      );
    }

    if (token === unavailableVendorPortalToken) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "This vendor portal link is no longer available." } },
        { status: 409 },
      );
    }

    return HttpResponse.json(
      { error: { code: "not_found", message: "This vendor portal link could not be found." } },
      { status: 404 },
    );
  }),
];
