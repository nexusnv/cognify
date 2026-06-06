import "@testing-library/jest-dom/vitest";
import { afterAll, afterEach, beforeAll, vi } from "vitest";
import { resetApprovalMockState } from "../features/approvals/mocks/approval-handlers";
import { resetAttachmentMockState } from "../features/attachments/mocks/attachments-handlers";
import { resetIdentityMockState } from "../features/identity/mocks/identity-handlers";
import { resetNotificationMockState } from "../features/notifications/mocks/notification-handlers";
import { resetRequisitionMockState } from "../features/requisitions/mocks/requisitions-handlers";
import { resetRfqMockState } from "../features/sourcing/mocks/rfq-handlers";
import { resetRfqInvitationMockState } from "../features/sourcing/mocks/rfq-invitation-handlers";
import { resetVendorMockState } from "../features/sourcing/mocks/vendor-handlers";
import { server } from "./msw/server";

Object.defineProperty(URL, "createObjectURL", {
  value: vi.fn(() => "blob:mock"),
  writable: true,
});

Object.defineProperty(URL, "revokeObjectURL", {
  value: vi.fn(),
  writable: true,
});

if (typeof Element !== "undefined" && !Element.prototype.scrollIntoView) {
  Element.prototype.scrollIntoView = vi.fn();
}

if (typeof window !== "undefined" && !window.matchMedia) {
  Object.defineProperty(window, "matchMedia", {
    writable: true,
    value: vi.fn().mockImplementation((query: string) => ({
      matches: false,
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    })),
  });
}

beforeAll(() => server.listen({ onUnhandledRequest: "error" }));
afterEach(() => {
  server.resetHandlers();
  resetApprovalMockState();
  resetAttachmentMockState();
  resetRequisitionMockState();
  resetRfqMockState();
  resetRfqInvitationMockState();
  resetVendorMockState();
  resetIdentityMockState();
  resetNotificationMockState();
});
afterAll(() => server.close());
