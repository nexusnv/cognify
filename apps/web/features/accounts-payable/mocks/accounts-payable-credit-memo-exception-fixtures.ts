import type { SupplierCreditMemoException } from "@cognify/api-client/schemas";

const _exceptions: SupplierCreditMemoException[] = [];

export const creditMemoExceptionFixtures = {
  all: () => _exceptions,
  setExceptions: (next: SupplierCreditMemoException[]) => {
    _exceptions.length = 0;
    _exceptions.push(...next);
  },
};
