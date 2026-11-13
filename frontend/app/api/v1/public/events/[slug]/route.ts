import { NextRequest, NextResponse } from "next/server";
import { eventStore } from "@/lib/demo-store";

export const dynamic = "force-dynamic";

export async function GET(
  _request: NextRequest,
  { params }: { params: Promise<{ slug: string }> }
) {
  const { slug } = await params;
  const event = eventStore.getBySlug(slug);

  if (!event || event.status !== "published") {
    return NextResponse.json(
      { message: "Event not found or is currently not published." },
      { status: 404 }
    );
  }

  return NextResponse.json({
    data: event,
  });
}
