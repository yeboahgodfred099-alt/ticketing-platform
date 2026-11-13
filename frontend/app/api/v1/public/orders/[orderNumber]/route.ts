import { NextRequest, NextResponse } from "next/server";

export async function GET(
  _request: NextRequest,
  { params }: { params: Promise<{ orderNumber: string }> }
) {
  const { orderNumber } = await params;

  return NextResponse.json({
    order: {
      order_number: orderNumber,
      event_name: "Accra Music & Culture Summit 2026",
      customer_name: "Kwame Mensah",
      customer_phone: "+233241234567",
      customer_email: "kwame@example.com",
      total_amount: 10000,
      currency: "GHS",
      status: "pending_payment",
      expires_at: new Date(Date.now() + 14 * 60 * 1000).toISOString(),
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
  });
}

