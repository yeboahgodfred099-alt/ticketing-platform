import { NextRequest, NextResponse } from "next/server";
import { eventStore } from "@/lib/demo-store";

export const dynamic = "force-dynamic";

export async function GET() {
  return NextResponse.json({
    data: eventStore.getAll(),
  });
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const newEvent = eventStore.create(body);

    return NextResponse.json(
      {
        data: newEvent,
        message: "Event created successfully.",
      },
      { status: 201 }
    );
  } catch {
    return NextResponse.json(
      { message: "Invalid event data." },
      { status: 400 }
    );
  }
}
