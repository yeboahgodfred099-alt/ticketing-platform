import { NextRequest, NextResponse } from "next/server";

const DEMO_EVENT_DETAILS: Record<string, unknown> = {
  "accra-music-summit-2026": {
    id: "e1a90c00-2026-4000-8000-000000000001",
    name: "Accra Music & Culture Summit 2026",
    slug: "accra-music-summit-2026",
    description: "The premier gathering of music innovators, artists, and live performances at Black Star Square.\n\nExperience two stages of non-stop live music, cultural showcases, art installations, and premier Ghanaian cuisine.",
    venue_name: "Accra International Conference Centre",
    venue_address: "Castle Road, Osu, Accra, Ghana",
    banner_url: null,
    starts_at: "2026-12-20T18:00:00Z",
    ends_at: "2026-12-21T02:00:00Z",
    sales_starts_at: "2026-11-01T00:00:00Z",
    sales_ends_at: "2026-12-20T17:00:00Z",
    timezone: "Africa/Accra",
    is_sold_out: false,
    ticket_types: [
      {
        id: "t1-early-bird",
        name: "Early Bird Admission",
        description: "Discounted general admission with access to all main stages and festival grounds.",
        price_minor: 5000,
        currency: "GHS",
        quantity_total: 200,
        quantity_available: 48,
        min_per_order: 1,
        max_per_order: 5,
        is_sold_out: false,
      },
      {
        id: "t2-standard",
        name: "Standard Admission",
        description: "Full access admission to summit sessions, daylight exhibitions, and evening concert.",
        price_minor: 10000,
        currency: "GHS",
        quantity_total: 500,
        quantity_available: 312,
        min_per_order: 1,
        max_per_order: 10,
        is_sold_out: false,
      },
      {
        id: "t3-vip",
        name: "VIP Golden Circle",
        description: "Premium lounge seating, welcome drinks, priority gate entrance, and artist meet & greet lounge.",
        price_minor: 35000,
        currency: "GHS",
        quantity_total: 50,
        quantity_available: 14,
        min_per_order: 1,
        max_per_order: 4,
        is_sold_out: false,
      },
    ],
  },
  "ghana-tech-founders-expo-2026": {
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
    is_sold_out: false,
    ticket_types: [
      {
        id: "t4-expo-pass",
        name: "Delegate Pass",
        description: "Access to all keynotes, breakout workshops, and exhibition hall.",
        price_minor: 15000,
        currency: "GHS",
        quantity_total: 400,
        quantity_available: 195,
        min_per_order: 1,
        max_per_order: 6,
        is_sold_out: false,
      },
    ],
  },
};

export async function GET(
  _request: NextRequest,
  { params }: { params: Promise<{ slug: string }> }
) {
  const { slug } = await params;
  const event = DEMO_EVENT_DETAILS[slug];

  if (!event) {
    return NextResponse.json(
      { message: "Event not found." },
      { status: 404 }
    );
  }

  return NextResponse.json({
    data: event,
  });
}

