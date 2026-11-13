import { NextRequest, NextResponse } from "next/server";

export async function GET(
  _request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params;

  return NextResponse.json({
    data: {
      id,
      name: "Accra Music & Culture Summit 2026",
      slug: "accra-music-summit-2026",
      description: "The premier gathering of music innovators, artists, and live performances at Black Star Square.",
      venue_name: "Accra International Conference Centre",
      venue_address: "Castle Road, Osu, Accra, Ghana",
      banner_url: null,
      starts_at: "2026-12-20T18:00:00Z",
      ends_at: "2026-12-21T02:00:00Z",
      sales_starts_at: "2026-11-01T00:00:00Z",
      sales_ends_at: "2026-12-20T17:00:00Z",
      timezone: "Africa/Accra",
      status: "published",
      total_capacity: 750,
      total_sold: 450,
    },
  });
}
