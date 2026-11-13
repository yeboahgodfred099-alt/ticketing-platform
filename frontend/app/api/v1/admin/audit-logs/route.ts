import { NextResponse } from "next/server";

export async function GET() {
  return NextResponse.json({
    data: [
      {
        id: "log-001",
        action: "event.publish",
        admin_id: "a1b2c3d4",
        admin_name: "Kwesi Appiah",
        admin_email: "admin@clevawave.com",
        target_type: "Event",
        target_id: "e1a90c00-2026-4000-8000-000000000001",
        ip_address: "197.251.134.12",
        user_agent: "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)",
        metadata: {
          event_name: "Accra Music & Culture Summit 2026",
          status_from: "draft",
          status_to: "published",
        },
        created_at: new Date(Date.now() - 3600000).toISOString(),
      },
      {
        id: "log-002",
        action: "ticket_type.update",
        admin_id: "a1b2c3d4",
        admin_name: "Kwesi Appiah",
        admin_email: "admin@clevawave.com",
        target_type: "TicketType",
        target_id: "t2-standard",
        ip_address: "197.251.134.12",
        user_agent: "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)",
        metadata: {
          name: "Standard Admission",
          quantity_total_old: 400,
          quantity_total_new: 500,
        },
        created_at: new Date(Date.now() - 7200000).toISOString(),
      },
      {
        id: "log-003",
        action: "check_in.scan",
        admin_id: "a1b2c3d4",
        admin_name: "Kwesi Appiah",
        admin_email: "admin@clevawave.com",
        target_type: "Ticket",
        target_id: "TCK-ORD-DEMO-001",
        ip_address: "41.215.168.45",
        user_agent: "Mobile Chrome / Android 14",
        metadata: {
          outcome: "valid",
          tier: "VIP Golden Circle",
        },
        created_at: new Date(Date.now() - 1800000).toISOString(),
      },
    ],
    meta: {
      current_page: 1,
      from: 1,
      last_page: 1,
      per_page: 25,
      to: 3,
      total: 3,
    },
  });
}

