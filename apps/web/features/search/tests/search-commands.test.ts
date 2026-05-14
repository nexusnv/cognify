import { describe, expect, it } from "vitest";
import { requesterIdentity } from "../../identity/mocks/identity-fixtures";
import { getSearchCommands } from "../search-commands";

describe("getSearchCommands", () => {
  it("hides navigation and create actions until permissions are available", () => {
    expect(getSearchCommands(null)).toEqual([]);
  });

  it("returns permitted commands after permissions are available", () => {
    const commands = getSearchCommands(requesterIdentity.permissions);

    expect(commands.some((command) => command.label === "Open requisitions")).toBe(true);
    expect(commands.some((command) => command.label === "Create requisition")).toBe(true);
  });
});
