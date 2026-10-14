import { clsx, type ClassValue } from "clsx"
import { twMerge } from "tailwind-merge"
import { format, isValid, parseISO } from "date-fns"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function formatMoney(amount: number, currency: string = "USD"): string {
  const isKhr = currency === "KHR";
  const symbol = isKhr ? "៛" : "$";
  const digits = isKhr ? 0 : 2;

  const formatted = new Intl.NumberFormat("en-US", {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(amount);

  // Non-breaking space: keeps "$ 1,299.00" on one line when columns tighten.
  return formatted.startsWith("-")
    ? `-${symbol}\u00A0${formatted.slice(1)}`
    : `${symbol}\u00A0${formatted}`;
}

// parseISO (not new Date) so date-only strings like "2026-10-01" stay in
// local time instead of shifting a day via UTC parsing.
function toDateOrNull(value: string | Date | null | undefined): Date | null {
  if (!value) return null;
  const date = value instanceof Date ? value : parseISO(value);
  return isValid(date) ? date : null;
}

export function formatDate(
  value: string | Date | null | undefined,
  pattern = "dd MMM yyyy",
): string {
  const date = toDateOrNull(value);
  return date ? format(date, pattern) : "—";
}

export function formatDateTime(
  value: string | Date | null | undefined,
  pattern = "dd MMM yyyy, HH:mm",
): string {
  const date = toDateOrNull(value);
  return date ? format(date, pattern) : "—";
}
