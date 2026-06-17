import "@testing-library/jest-dom/vitest";
import { cleanup } from "@testing-library/react";
import { afterAll, afterEach, beforeAll, vi } from "vitest";

// Suppress React 18 act() warnings from Radix UI portal components (Select, Dialog, etc.)
// Radix uses flushSync internally, which triggers unstoppable warnings that are not actionable.
const originalConsoleError = console.error;
console.error = (...args: unknown[]) => {
  for (const arg of args) {
    if (typeof arg === "string") {
      if (
        arg.includes("not wrapped in act(") ||
        arg.includes("suspended inside an `act` scope")
      ) {
        return;
      }
    }
  }
  originalConsoleError.apply(console, args);
};

// Disable CSS animations and transitions so Radix portals (Select, Dialog, etc.)
// and other animated components settle synchronously.
if (typeof document !== "undefined") {
  const disableAnimations = document.createElement("style");
  disableAnimations.textContent = `*,*::before,*::after{animation-duration:0s!important;transition-duration:0s!important;}`;
  document.head.appendChild(disableAnimations);
}
import { resetAccountsPayableInvoiceMockState } from "../features/accounts-payable/mocks/accounts-payable-invoice-handlers";
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

if (typeof window !== "undefined" && !window.ResizeObserver) {
  class ResizeObserver {
    observe() {}
    unobserve() {}
    disconnect() {}
  }

  window.ResizeObserver = ResizeObserver as unknown as typeof window.ResizeObserver;
  globalThis.ResizeObserver = ResizeObserver as unknown as typeof globalThis.ResizeObserver;
}

if (typeof window !== "undefined" && !window.matchMedia) {
  Object.defineProperty(window, "matchMedia", {
    writable: true,
    value: vi.fn().mockImplementation((query: string) => ({
      matches: query.includes("min-width"),
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
  cleanup();
  server.resetHandlers();
  resetAccountsPayableInvoiceMockState();
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
