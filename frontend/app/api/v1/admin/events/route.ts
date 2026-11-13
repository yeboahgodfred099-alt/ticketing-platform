import { NextRequest, NextResponse } from "next/server";

const STORED_EVENTS = [
  {
    id: "e1a90c00-2026-4000-8000-000000000001",
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
    ticket_types: [
      { id: "t1", name: "Early Bird Admission" },
      { id: "t2", name: "Standard Admission" },
      { id: "t3", name: "VIP Golden Circle" },
    ],
  },
  {
    id: "e2b90c00-2026-4000-8000-000000000002",
    name: "Ghana Tech Founders Expo 2026",
    slug: "ghana-tech-founders-expo-2026",
    description: "Connect with West Africa's leading technology startups, venture investors, and product innovators.",
    venue_name: "Grand Arena, Accra",
    venue_address: "Independence Avenue, Accra, Ghana",
    banner_url: null,
    starts_at: "2026-12-28T09:00:00Z",
    ends_at: "2026-12-28T18:00:00Z",
    sales_starts_at: "2026-11-01T00:00:00Z",
    sales_ends_at: "2026-12-28T08:00:00Z",
    timezone: "Africa/Accra",
    status: "draft",
    total_capacity: 400,
    total_sold: 0,
    ticket_types: [{ id: "t4", name: "Delegate Pass" }],
  },
];

export async function GET() {
  return NextResponse.json({
    data: STORED_EVENTS,
  });
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();

    const newId = `evt-${Date.now()}`;
    const newEvent = {
      id: newId,
      name: body.name || "Untitled Event",
      slug: body.slug || `event-${Date.now()}`,
      description: body.description || "",
      venue_name: body.venue_name || "",
      venue_address: body.venue_address || "",
      starts_at: body.starts_at || new Date(Date.now() + 86400000 * 14).toISOString(),
      ends_at: body.ends_at || new Date(Date.now() + 86400000 * 14 + 18000000).toISOString(),
      sales_starts_at: body.sales_starts_at || null,
      sales_ends_at: body.sales_ends_at || null,
      timezone: body.timezone || "Africa/Accra",
      status: "draft",
      banner_url: null,
      total_capacity: 0,
      total_sold: 0,
      ticket_types: [],
    };

    STORED_EVENTS.push(newEvent);

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
