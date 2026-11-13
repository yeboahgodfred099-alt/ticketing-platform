import { NextResponse } from "next/server";

export async function GET() {
  return NextResponse.json({
    data: [
      {
        id: "att-001",
        ticket_number: "TCK-ORD-2026-8921-1",
        event_name: "Accra Music & Culture Summit 2026",
        tier_name: "Standard Admission",
        attendee_name: "Kwame Mensah",
        attendee_phone: "+233 24 *** *567",
        status: "used",
        checked_in_at: new Date(Date.now() - 1800000).toISOString(),
      },
      {
        id: "att-002",
        ticket_number: "TCK-ORD-2026-8922-1",
        event_name: "Accra Music & Culture Summit 2026",
        tier_name: "VIP Golden Circle",
        attendee_name: "Ama Serwaa",
        attendee_phone: "+233 50 *** *890",
        status: "active",
        checked_in_at: null,
      },
      {
        id: "att-003",
        ticket_number: "TCK-ORD-2026-8919-1",
        event_name: "Accra Music & Culture Summit 2026",
        tier_name: "Early Bird Admission",
        attendee_name: "Yaw Frimpong",
        attendee_phone: "+233 24 *** *333",
        status: "active",
        checked_in_at: null,
      },
    ],
  });
}
