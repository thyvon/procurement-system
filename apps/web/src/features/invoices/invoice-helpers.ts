const PAYMENT_TERM_KEYS = [
  "non_credit",
  "credit_1w",
  "credit_2w",
  "credit_1m",
] as const;
const TX_TYPE_KEYS = ["petty_cash", "advance", "credit"] as const;

type FormLabelKey =
  | "paymentTerms.nonCredit"
  | "paymentTerms.credit1w"
  | "paymentTerms.credit2w"
  | "paymentTerms.credit1m"
  | "txTypes.pettyCash"
  | "txTypes.advance"
  | "txTypes.credit";

type FormTranslator = (key: FormLabelKey) => string;

export type PaymentTermOption = {
  value: (typeof PAYMENT_TERM_KEYS)[number];
  label: string;
};

export type TxTypeOption = {
  value: (typeof TX_TYPE_KEYS)[number];
  label: string;
};

function paymentTermLabels(t: FormTranslator): Record<string, string> {
  return {
    non_credit: t("paymentTerms.nonCredit"),
    credit_1w: t("paymentTerms.credit1w"),
    credit_2w: t("paymentTerms.credit2w"),
    credit_1m: t("paymentTerms.credit1m"),
  };
}

function txTypeLabels(t: FormTranslator): Record<string, string> {
  return {
    petty_cash: t("txTypes.pettyCash"),
    advance: t("txTypes.advance"),
    credit: t("txTypes.credit"),
  };
}

export function paymentTermLabel(
  t: FormTranslator,
  value: string | null | undefined
): string {
  if (!value) return "";
  return paymentTermLabels(t)[value] ?? value;
}

export function txTypeLabel(
  t: FormTranslator,
  value: string | null | undefined
): string {
  if (!value) return "";
  return txTypeLabels(t)[value] ?? value;
}

export function paymentTermOptions(t: FormTranslator): PaymentTermOption[] {
  return PAYMENT_TERM_KEYS.map((value) => ({
    value,
    label: paymentTermLabels(t)[value],
  }));
}

export function txTypeOptions(t: FormTranslator): TxTypeOption[] {
  return TX_TYPE_KEYS.map((value) => ({
    value,
    label: txTypeLabels(t)[value],
  }));
}

type DiscountLine = { discount?: string | number | null };

export function sumLineDiscounts(lines: readonly DiscountLine[]): number {
  return lines.reduce((sum, line) => {
    const parsed = Number.parseFloat(String(line.discount ?? ""));
    return sum + (Number.isFinite(parsed) ? parsed : 0);
  }, 0);
}

export function resolveLineDiscounts(
  lineDiscountTotal: number,
  headerDiscountAmount: number
): { headerDiscount: number; displayDiscount: number } {
  // Line discounts are already netted into line totals; when any line has
  // one, the header discount is ignored so the summary and the total math
  // tell the same story (mirrors the API).
  const hasLineDiscounts = lineDiscountTotal > 0;
  return {
    headerDiscount: hasLineDiscounts ? 0 : headerDiscountAmount,
    displayDiscount: hasLineDiscounts
      ? lineDiscountTotal
      : headerDiscountAmount,
  };
}
