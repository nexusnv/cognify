"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  deactivateScoringTemplate,
  getScoringTemplate,
  listScoringTemplates,
  saveScoringTemplate,
  SaveScoringTemplateInput,
} from "../api/quotation-scoring-api";

export const quotationScoringKeys = {
  templates: (tenantId: string | null) => ["quotation-scoring", tenantId ?? "no-tenant", "templates"] as const,
  template: (tenantId: string | null, templateId: string) =>
    ["quotation-scoring", tenantId ?? "no-tenant", "templates", templateId] as const,
  scorecard: (tenantId: string | null, rfqId: string) =>
    ["quotation-scoring", tenantId ?? "no-tenant", "scorecard", rfqId] as const,
};

export function useQuotationScoringTemplates() {
  const tenantId = getStoredActiveTenantId();

  return useQuery({
    queryKey: quotationScoringKeys.templates(tenantId),
    queryFn: () => listScoringTemplates(tenantId),
  });
}

export function useQuotationScoringTemplate(templateId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  const queryTemplateId = templateId ?? "new";

  return useQuery({
    queryKey: quotationScoringKeys.template(tenantId, queryTemplateId),
    queryFn: () => {
      if (!templateId || templateId === "new") {
        throw new Error("Cannot load a scoring template without a template id.");
      }

      return getScoringTemplate(templateId, tenantId);
    },
    enabled: Boolean(templateId && templateId !== "new"),
  });
}

export function useSaveQuotationScoringTemplate() {
  const tenantId = getStoredActiveTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: SaveScoringTemplateInput) => saveScoringTemplate(input, tenantId),
    onSuccess: async (template) => {
      await queryClient.invalidateQueries({ queryKey: quotationScoringKeys.templates(tenantId) });
      await queryClient.invalidateQueries({ queryKey: quotationScoringKeys.template(tenantId, template.id) });
    },
  });
}

export function useDeactivateQuotationScoringTemplate() {
  const tenantId = getStoredActiveTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (templateId: string) => deactivateScoringTemplate(templateId, tenantId),
    onSuccess: async (template) => {
      await queryClient.invalidateQueries({ queryKey: quotationScoringKeys.templates(tenantId) });
      await queryClient.invalidateQueries({ queryKey: quotationScoringKeys.template(tenantId, template.id) });
    },
  });
}
