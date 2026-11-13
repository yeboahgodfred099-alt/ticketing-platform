import { NextRequest, NextResponse } from "next/server";
import { eventStore } from "@/lib/demo-store";

export const dynamic = "force-dynamic";

export async function POST(
  _request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params;
  const event = eventStore.setBanner(
    id,
    "https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=1200"
  );

  return NextResponse.json({
    data: event,
    message: "Banner uploaded successfully.",
  });
}
