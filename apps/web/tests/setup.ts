import "@testing-library/jest-dom/vitest";
import { afterAll, afterEach, beforeAll, vi } from "vitest";
import { resetAttachmentMockState } from "../features/attachments/mocks/attachments-handlers";
import { resetIdentityMockState } from "../features/identity/mocks/identity-handlers";
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

beforeAll(() => server.listen({ onUnhandledRequest: "error" }));
afterEach(() => {
  server.resetHandlers();
  resetAttachmentMockState();
  resetRequisitionMockState();
  resetIdentityMockState();
});
afterAll(() => server.close());
