import { NextResponse } from "next/server";

export async function GET() {
  return NextResponse.json({
    data: {
      total_tickets: 500,
      checked_in: 342,
      remaining: 158,
      attendance_percentage: 68.4,
    },
  });
}

