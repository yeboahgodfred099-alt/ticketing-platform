import { NextRequest, NextResponse } from "next/server";

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const reference = `ORD-DEMO-${Date.now()}`;

    return NextResponse.json({
      status: true,
      message: "Charge attempted",
      data: {
        reference,
        status: "pay_offline",
        display_text: `Please authorize the prompt sent to ${body.phone || "your Mobile Money wallet"}`,
      },
    });
  } catch {
    return NextResponse.json(
      { message: "Payment initiation error." },
      { status: 500 }
    );
  }
}

