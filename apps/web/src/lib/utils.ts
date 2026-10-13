import { clsx, type ClassValue } from "clsx"
import { twMerge } from "tailwind-merge"

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
