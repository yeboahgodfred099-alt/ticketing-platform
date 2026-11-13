import { NextResponse } from "next/server";
import { eventStore } from "@/lib/demo-store";

export const dynamic = "force-dynamic";

export async function GET() {
  const publishedEvents = eventStore.getPublished();
  return NextResponse.json({
    data: publishedEvents,
  });
}
