import { NextRequest, NextResponse } from "next/server";

export async function GET(
  _request: NextRequest,
  { params }: { params: Promise<{ accessToken: string }> }
) {
  const { accessToken } = await params;

  return NextResponse.json({
    data: {
      order_number: "ORD-DEMO-2026",
      customer_name: "Kwame Mensah",
      customer_email: "kwame@example.com",
      event: {
        id: "e1a90c00-2026-4000-8000-000000000001",
        name: "Accra Music & Culture Summit 2026",
        slug: "accra-music-summit-2026",
        venue_name: "Accra International Conference Centre",
        venue_address: "Castle Road, Osu, Accra, Ghana",
        starts_at: "2026-12-20T18:00:00Z",
        ends_at: "2026-12-21T02:00:00Z",
        timezone: "Africa/Accra",
      },
      tickets: [
        {
          id: "tck-demo-001",
          ticket_number: "TCK-ORD-DEMO-001",
          ticket_type_name: "Standard Admission",
          attendee_name: "Kwame Mensah",
          attendee_phone: "+233 24 *** *567",
          status: "active",
          checked_in_at: null,
          qr_code_svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="220" height="220"><rect width="100" height="100" fill="#ffffff"/><path d="M10,10 h20 v20 h-20 z M70,10 h20 v20 h-20 z M10,70 h20 v20 h-20 z M15,15 h10 v10 h-10 z M75,15 h10 v10 h-10 z M15,75 h10 v10 h-10 z M40,20 h20 v10 h-20 z M45,40 h15 v20 h-15 z M30,60 h40 v10 h-40 z M65,70 h25 v15 h-25 z" fill="#000000"/></svg>`,
        },
      ],
    },
  });
}

