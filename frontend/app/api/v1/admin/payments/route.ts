import { NextResponse } from "next/server";

export async function GET() {
  return NextResponse.json({
    data: [
      {
        id: "pay-001",
        reference: "ORD-2026-8921-PAY-1",
        order_number: "ORD-2026-8921",
        channel: "mobile_money",
        provider: "mtn",
        amount: 10000,
        fee_amount: 195,
        currency: "GHS",
        status: "successful",
        paid_at: new Date(Date.now() - 3600000 * 2).toISOString(),
      },
      {
        id: "pay-002",
        reference: "ORD-2026-8922-PAY-1",
        order_number: "ORD-2026-8922",
        channel: "mobile_money",
        provider: "vod",
        amount: 35000,
        fee_amount: 683,
        currency: "GHS",
        status: "successful",
        paid_at: new Date(Date.now() - 3600000 * 4).toISOString(),
      },
      {
        id: "pay-003",
        reference: "ORD-2026-8923-PAY-1",
        order_number: "ORD-2026-8923",
        channel: "mobile_money",
        provider: "atl",
        amount: 15000,
        fee_amount: 293,
        currency: "GHS",
        status: "pending",
        paid_at: null,
      },
    ],
  });
}
