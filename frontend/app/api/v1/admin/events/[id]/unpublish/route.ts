import { NextRequest, NextResponse } from "next/server";
import { eventStore } from "@/lib/demo-store";

export const dynamic = "force-dynamic";

export async function POST(
  _request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params;
  const event = eventStore.setStatus(id, "unpublished");

  if (!event) {
    return NextResponse.json({ message: "Event not found." }, { status: 404 });
  }

  return NextResponse.json({
    data: event,
    message: "Event unpublished successfully.",
  });
}
