import { NextResponse } from "next/server";

export async function GET() {
  return NextResponse.json({
    data: {
      revenue: {
        gross_pesewas: 3450000, // GH₵ 34,500.00
        absorbed_fees_pesewas: 67275, // GH₵ 672.75
        net_pesewas: 3382725, // GH₵ 33,827.25
      },
      orders: {
        total_orders: 284,
        total_paid_orders: 268,
        pending_orders: 16,
        conversion_rate: 94.37,
      },
      tickets: {
        total_sold: 450,
        total_reserved: 24,
      },
      attendance: {
        checked_in: 342,
        attendance_rate: 76.0,
      },
      providers: [
        {
          provider: "mtn",
          count: 184,
          total_pesewas: 2350000,
        },
        {
          provider: "vod",
          count: 62,
          total_pesewas: 810000,
        },
        {
          provider: "atl",
          count: 22,
          total_pesewas: 290000,
        },
      ],
      tiers: [
        {
          id: "t1-early",
          name: "Early Bird Admission",
          price_pesewas: 5000,
          sold: 200,
          reserved: 0,
          total: 200,
          revenue_pesewas: 1000000,
        },
        {
          id: "t2-standard",
          name: "Standard Admission",
          price_pesewas: 10000,
          sold: 215,
          reserved: 18,
          total: 500,
          revenue_pesewas: 2150000,
        },
        {
          id: "t3-vip",
          name: "VIP Golden Circle",
          price_pesewas: 35000,
          sold: 35,
          reserved: 6,
          total: 50,
          revenue_pesewas: 1225000,
        },
      ],
    },
  });
}

