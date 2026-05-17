import { http, HttpResponse } from "msw";
import { approvalPolicyFixture } from "./approval-fixtures";
import type { ApprovalPolicy } from "../types/approval-view-model";

let policies: ApprovalPolicy[] = [structuredClone(approvalPolicyFixture)];

type ApprovalPolicyPayload = Partial<ApprovalPolicy> & Pick<Partial<ApprovalPolicy["versions"][number]>, "rules" | "routeTemplate" | "slaRules">;

export function resetApprovalMockState() {
  policies = [structuredClone(approvalPolicyFixture)];
}

export const approvalHandlers = [
  http.get("/api/approval-policies", () => HttpResponse.json({ data: policies })),
  http.get("/api/approval-policies/:policyId", ({ params }) => {
    const policy = policies.find((item) => item.id === params.policyId);
    if (!policy) return HttpResponse.json({ message: "Not found" }, { status: 404 });
    return HttpResponse.json({ data: policy });
  }),
  http.post("/api/approval-policies", async ({ request }) => {
    const body = (await request.json()) as ApprovalPolicyPayload;
    const policy: ApprovalPolicy = {
      ...structuredClone(approvalPolicyFixture),
      id: `ap-${policies.length + 101}`,
      name: body.name ?? "Untitled policy",
      description: body.description ?? "",
      subjectType: "requisition",
      versions: [
        {
          ...structuredClone(approvalPolicyFixture.versions[0]!),
          id: `apv-${policies.length + 101}`,
          policyId: `ap-${policies.length + 101}`,
          rules: body.rules ?? [],
          routeTemplate:
            body.routeTemplate ??
            structuredClone(approvalPolicyFixture.versions[0]!.routeTemplate),
          slaRules: body.slaRules ?? [],
        },
      ],
    };
    policies.push(policy);
    return HttpResponse.json({ data: policy }, { status: 201 });
  }),
  http.patch("/api/approval-policies/:policyId", async ({ params, request }) => {
    const body = (await request.json()) as Partial<ApprovalPolicy>;
    const index = policies.findIndex((item) => item.id === params.policyId);
    if (index === -1) return HttpResponse.json({ message: "Not found" }, { status: 404 });
    policies[index] = { ...policies[index]!, ...body };
    return HttpResponse.json({ data: policies[index] });
  }),
  http.post("/api/approval-policy-versions/:versionId/publish", ({ params }) => {
    const policy = policies.find((item) =>
      item.versions.some((version) => version.id === params.versionId),
    );
    const version = policy?.versions.find((item) => item.id === params.versionId);
    if (!policy || !version) return HttpResponse.json({ message: "Not found" }, { status: 404 });
    policy.status = "active";
    version.status = "published";
    return HttpResponse.json({ data: version });
  }),
];
