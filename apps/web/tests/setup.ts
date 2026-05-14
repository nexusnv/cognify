import "@testing-library/jest-dom/vitest";
import { afterAll, afterEach, beforeAll, vi } from "vitest";
import { resetAttachmentMockState } from "../features/attachments/mocks/attachments-handlers";
import { resetIdentityMockState } from "../features/identity/mocks/identity-handlers";
import { resetNotificationMockState } from "../features/notifications/mocks/notification-handlers";
import { resetRequisitionMockState } from "../features/requisitions/mocks/requisitions-handlers";
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

beforeAll(() => server.listen({ onUnhandledRequest: "error" }));
afterEach(() => {
  server.resetHandlers();
  resetAttachmentMockState();
  resetRequisitionMockState();
  resetIdentityMockState();
  resetNotificationMockState();
});
afterAll(() => server.close());
