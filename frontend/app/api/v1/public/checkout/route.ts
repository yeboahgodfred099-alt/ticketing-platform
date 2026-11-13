import { NextRequest, NextResponse } from "next/server";

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const orderNumber = `ORD-DEMO-${Math.floor(1000 + Math.random() * 9000)}`;

    return NextResponse.json({
      order: {
        order_number: orderNumber,
        event_name: "Accra Music & Culture Summit 2026",
        customer_name: body.customer_name || "Kwame Mensah",
        customer_phone: body.customer_phone || "+233241234567",
        customer_email: body.customer_email || "kwame@example.com",
        total_amount: 10000,
        currency: "GHS",
        status: "pending_payment",
        expires_at: new Date(Date.now() + 15 * 60 * 1000).toISOString(),
        order_access_token: "demo-access-token-64char-hex-clevawave-preview",
        items: [
          {
            ticket_type_name: "Standard Admission",
            quantity: 1,
            unit_price: 10000,
            subtotal: 10000,
          },
        ],
      },
      message: "Order reserved successfully.",
    });
  } catch {
    return NextResponse.json(
      { message: "Invalid checkout request." },
      { status: 400 }
    );
  }
}

