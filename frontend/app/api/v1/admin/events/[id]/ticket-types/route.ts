import { NextRequest, NextResponse } from "next/server";
import { eventStore } from "@/lib/demo-store";

export const dynamic = "force-dynamic";

export async function GET(
  _request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params;
  const event = eventStore.getById(id);

  return NextResponse.json({
    data: event?.ticket_types || [],
  });
}

export async function POST(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const { id } = await params;
    const body = await request.json();
    const newTicket = eventStore.addTicketType(id, body);

    if (!newTicket) {
      return NextResponse.json({ message: "Event not found." }, { status: 404 });
    }

    return NextResponse.json({
      data: newTicket,
      message: "Ticket category added successfully.",
    });
  } catch {
    return NextResponse.json({ message: "Invalid ticket type data." }, { status: 400 });
  }
}
