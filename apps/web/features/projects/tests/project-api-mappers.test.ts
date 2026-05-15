import { describe, expect, it } from "vitest";
import { mapProject, mapProjectListResponse } from "../api/projects-api";
import { projectListResponseFixture, projectResponseFixture } from "../mocks/project-fixtures";

describe("project API mappers", () => {
  it("maps project detail responses into the view model", () => {
    const project = mapProject(projectResponseFixture.data);

    expect(project.id).toBe("501");
    expect(project.status).toBe("active");
    expect(project.owner.name).toBe("Priya Buyer");
    expect(project.summary.linkedRequisitionCount).toBe(2);
    expect(project.permissions.canLinkRequisitions).toBe(true);
  });

  it("maps project list responses with pagination metadata", () => {
    const response = mapProjectListResponse(projectListResponseFixture);

    expect(response.data).toHaveLength(1);
    expect(response.meta.total).toBe(1);
    expect(response.data[0]?.number).toBe("PRJ-2026-000501");
  });
});
