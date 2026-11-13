import { NextRequest, NextResponse } from "next/server";

const DEMO_TICKETS = [
  {
    id: "t1-early-bird",
    name: "Early Bird Admission",
    description: "Discounted general admission with access to all main stages.",
    price_minor: 5000,
    currency: "GHS",
    quantity_total: 200,
    quantity_sold: 200,
    quantity_reserved: 0,
    available_quantity: 0,
    min_per_order: 1,
    max_per_order: 5,
    status: "active",
  },
  {
    id: "t2-standard",
    name: "Standard Admission",
    description: "Full access admission to summit sessions and evening concert.",
    price_minor: 10000,
    currency: "GHS",
    quantity_total: 500,
    quantity_sold: 215,
    quantity_reserved: 18,
    available_quantity: 267,
    min_per_order: 1,
    max_per_order: 10,
    status: "active",
  },
  {
    id: "t3-vip",
    name: "VIP Golden Circle",
    description: "Premium lounge seating, welcome drinks, priority entrance.",
    price_minor: 35000,
    currency: "GHS",
    quantity_total: 50,
    quantity_sold: 35,
    quantity_reserved: 6,
    available_quantity: 9,
    min_per_order: 1,
    max_per_order: 4,
    status: "active",
  },
];

export async function GET() {
  return NextResponse.json({
    data: DEMO_TICKETS,
  });
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const newTicket = {
      id: `tt-${Date.now()}`,
      name: body.name,
      description: body.description || "",
      price_minor: body.price_minor || 0,
      currency: "GHS",
      quantity_total: body.quantity_total || 100,
      quantity_sold: 0,
      quantity_reserved: 0,
      available_quantity: body.quantity_total || 100,
      min_per_order: body.min_per_order || 1,
      max_per_order: body.max_per_order || 10,
      status: "active",
    };

    DEMO_TICKETS.push(newTicket);

    return NextResponse.json({
      data: newTicket,
      message: "Ticket category added successfully.",
    });
  } catch {
    return NextResponse.json({ message: "Invalid ticket type data." }, { status: 400 });
  }
}
