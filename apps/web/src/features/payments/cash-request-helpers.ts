export type CashRequestType = "petty_cash" | "advance" | "cash";

/** A cash request line while it is being edited — amount stays a raw string. */
export type LineForm = {
  description: string;
  currency: "USD" | "KHR";
  amount: string;
};

export const EMPTY_LINE: LineForm = {
  description: "",
  currency: "USD",
  amount: "",
};

// w-36 (not w-32) so the longest label — "Mode of payment" plus its required
// mark and colon — stays on one line; matches the detail page's MetaRow
// label column.
export const fieldLabelClass =
  "w-36 shrink-0 text-left after:ml-1 after:content-[':']";

type FormLabelKey = "txTypes.pettyCash" | "txTypes.advance" | "txTypes.cash";

type FormTranslator = (key: FormLabelKey) => string;

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

export function toNumber(value: string): number {
  const parsed = Number.parseFloat(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

export function toDateInput(date: Date | undefined): string | null {
  if (!date) return null;
  const year = date.getFullYear();
  const month = `${date.getMonth() + 1}`.padStart(2, "0");
  const day = `${date.getDate()}`.padStart(2, "0");
  return `${year}-${month}-${day}`;
}

export function toDate(value: string | null | undefined): Date | undefined {
  if (!value) return undefined;
  const [year, month, day] = value.split("-").map(Number);
  if (!year || !month || !day) return undefined;
  return new Date(year, month - 1, day);
}

export function hasLineError(line: LineForm): boolean {
  // Every cell is required by the API — an empty number input reads as 0, so
  // the raw strings must be checked for money too.
  return (
    !line.description.trim() ||
    line.amount.trim() === "" ||
    toNumber(line.amount) < 0
  );
}
