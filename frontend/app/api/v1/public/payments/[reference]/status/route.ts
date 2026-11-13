import { NextRequest, NextResponse } from "next/server";

export async function GET(
  _request: NextRequest,
  { params }: { params: Promise<{ reference: string }> }
) {
  const { reference } = await params;

  return NextResponse.json({
    reference,
    status: "success",
    is_paid: true,
    amount: 10000,
    currency: "GHS",
    paid_at: new Date().toISOString(),
    ticket_access_token: "demo-access-token-64char-hex-clevawave-preview",
  });
}

