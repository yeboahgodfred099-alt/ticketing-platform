import { NextResponse } from "next/server";

export async function GET() {
  return NextResponse.json({
    admin: {
      id: "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      name: "Kwesi Appiah",
      email: "admin@clevawave.com",
      is_active: true,
      has_mfa: true,
      email_verified: true,
      last_login_at: new Date().toISOString(),
      roles: [
        {
          id: "r-super-admin",
          name: "Super Admin",
          description: "Full administrative privileges across all modules and settings",
          permissions: [
            { id: "p1", name: "events.view" },
            { id: "p2", name: "events.create" },
            { id: "p3", name: "reports.view" },
            { id: "p4", name: "orders.export" },
            { id: "p5", name: "attendees.export" },
            { id: "p6", name: "audit_logs.view" },
            { id: "p7", name: "check_in.scan" },
          ],
        },
      ],
    },
  });
}

