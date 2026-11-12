import { type ClassValue, clsx } from "clsx";
import { twMerge } from "tailwind-merge";

/**
 * Combines Tailwind classes with clsx and twMerge.
 */
export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

/**
 * Format integer minor units (pesewas) to formatted currency string.
 * Example: 10000 pesewas -> "GH₵ 100.00"
 */
export function formatMoney(
  amountMinor: number | string | bigint,
  currency: string = "GHS"
): string {
  const numericAmount = typeof amountMinor === "bigint"
    ? Number(amountMinor)
    : Number(amountMinor);

  if (isNaN(numericAmount)) {
    return "GH₵ 0.00";
  }

  const majorUnits = numericAmount / 100;
  const formatted = new Intl.NumberFormat("en-GH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(majorUnits);

  return currency === "GHS" ? `GH₵ ${formatted}` : `${currency} ${formatted}`;
}

/**
 * Format date in Africa/Accra timezone.
 */
export function formatEventDate(
  dateString: string,
  timeZone: string = "Africa/Accra",
  includeTime: boolean = true
): string {
  try {
    const date = new Date(dateString);
    const options: Intl.DateTimeFormatOptions = {
      timeZone,
      weekday: "short",
      year: "numeric",
      month: "short",
      day: "numeric",
      ...(includeTime ? { hour: "numeric", minute: "2-digit", hour12: true } : {}),
    };
    return new Intl.DateTimeFormat("en-GH", options).format(date);
  } catch {
    return dateString;
  }
}

