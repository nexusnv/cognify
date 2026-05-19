import type {
  Rfq as ApiRfq,
  RfqLineItem as ApiRfqLineItem,
  RfqRequiredDocument as ApiRfqRequiredDocument,
  RfqStatus as ApiRfqStatus,
} from "@cognify/api-client/schemas";

export type RfqStatus = ApiRfqStatus;
export type RfqLineItem = ApiRfqLineItem;
export type RfqRequiredDocument = ApiRfqRequiredDocument;
export type RfqDraft = ApiRfq;

export function toRfqDraftViewModel(rfq: ApiRfq): RfqDraft {
  return rfq;
}
