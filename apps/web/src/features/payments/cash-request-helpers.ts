const TYPE_KEYS = ["petty_cash", "advance", "cash"] as const;

export type CashRequestType = (typeof TYPE_KEYS)[number];

type FormLabelKey = "txTypes.pettyCash" | "txTypes.advance" | "txTypes.cash";

type FormTranslator = (key: FormLabelKey) => string;

export type CashRequestTypeOption = {
  value: CashRequestType;
  label: string;
};

function typeLabels(t: FormTranslator): Record<string, string> {
  return {
    petty_cash: t("txTypes.pettyCash"),
    advance: t("txTypes.advance"),
    cash: t("txTypes.cash"),
  };
}

export function typeLabel(
  t: FormTranslator,
  value: string | null | undefined
): string {
  if (!value) return "";
  return typeLabels(t)[value] ?? value;
}

export function typeOptions(t: FormTranslator): CashRequestTypeOption[] {
  return TYPE_KEYS.map((value) => ({
    value,
    label: typeLabels(t)[value],
  }));
}

/**
 * Approval vocabulary for cash requests — the API's status listener maps one
 * subject type per request type onto the shared CashRequest model.
 */
export function approvalSubjectType(type: string | null | undefined): string {
  switch (type) {
    case "petty_cash":
      return "petty_cash_request";
    case "advance":
      return "advance_request";
    case "cash":
      return "cash_request";
    default:
      return "";
  }
}

/** The payment block (mode, payee, bank trio) belongs to advance/cash only. */
export function showsPaymentFields(type: string | null | undefined): boolean {
  return type === "advance" || type === "cash";
}

/** "Adv. request for" is an advance-only field (the API's required_if). */
export function showsAdvanceRequestFor(
  type: string | null | undefined
): boolean {
  return type === "advance";
}

/**
 * Draft preview mirror of the server-side USD conversion
 * (App\Support\Currency::toUsd) — the API recomputes the authoritative
 * amount_usd / total_usd on save.
 */
export function lineAmountUsd(
  amount: number,
  currency: string,
  exchangeRate: number
): number {
  if (currency !== "KHR") return amount;
  const rate =
    Number.isFinite(exchangeRate) && exchangeRate > 0 ? exchangeRate : 1;
  return Math.round((amount / rate) * 100) / 100;
}
