import { NextRequest, NextResponse } from "next/server";

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const token = body.token || "";

    // Simulated check-in outcomes based on input
    if (token.includes("USED")) {
      return NextResponse.json({
        outcome: "already_used",
        message: "Ticket already checked in.",
        checked_in_at: new Date().toISOString(),
      });
    }

    if (token.includes("WRONG")) {
      return NextResponse.json({
        outcome: "wrong_event",
        message: "Ticket belongs to a different event.",
      });
    }

    return NextResponse.json({
      outcome: "valid",
      message: "Check-in successful. Admitted.",
      ticket: {
        id: "tck-demo-001",
        ticket_number: "TCK-ORD-DEMO-001",
        tier_name: "Standard Admission",
        attendee_name: "Kwame Mensah",
        checked_in_at: new Date().toISOString(),
      },
    });
  } catch {
    return NextResponse.json(
      { outcome: "invalid", message: "Invalid scan payload." },
      { status: 400 }
    );
  }
}

