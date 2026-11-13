/**
 * Application Constants
 */

export const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_URL || "/api/v1";

export const GHANA_MOBILE_MONEY_NETWORKS = [
  {
    code: "mtn",
    name: "MTN Mobile Money",
    shortName: "MTN MoMo",
    badgeColor: "bg-yellow-400 text-yellow-950",
  },
  {
    code: "atl",
    name: "AirtelTigo / AT Money",
    shortName: "AT Money",
    badgeColor: "bg-blue-500 text-white",
  },
  {
    code: "vod",
    name: "Telecel Cash",
    shortName: "Telecel",
    badgeColor: "bg-red-500 text-white",
  },
] as const;

export type MobileMoneyProviderCode =
  (typeof GHANA_MOBILE_MONEY_NETWORKS)[number]["code"];

