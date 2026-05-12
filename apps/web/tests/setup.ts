import "@testing-library/jest-dom/vitest";
import { afterAll, afterEach, beforeAll } from "vitest";
import { resetIdentityMockState } from "../features/identity/mocks/identity-handlers";
import { resetRequisitionMockState } from "../features/requisitions/mocks/requisitions-handlers";
import { server } from "./msw/server";

beforeAll(() => server.listen({ onUnhandledRequest: "error" }));
afterEach(() => {
  server.resetHandlers();
  resetRequisitionMockState();
  resetIdentityMockState();
});
afterAll(() => server.close());
