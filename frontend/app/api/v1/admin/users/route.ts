import { NextResponse } from "next/server";

export async function GET() {
  return NextResponse.json({
    data: [
      {
        id: "usr-001",
        name: "Kwesi Appiah",
        email: "admin@clevawave.com",
        role: "Super Admin",
        is_active: true,
        has_mfa: true,
        last_login_at: new Date(Date.now() - 3600000).toISOString(),
      },
      {
        id: "usr-002",
        name: "Akosua Mensah",
        email: "akosua@clevawave.com",
        role: "Event Manager",
        is_active: true,
        has_mfa: true,
        last_login_at: new Date(Date.now() - 3600000 * 24).toISOString(),
      },
      {
        id: "usr-003",
        name: "Ekow Taylor",
        email: "ekow@clevawave.com",
        role: "Finance Manager",
        is_active: true,
        has_mfa: true,
        last_login_at: new Date(Date.now() - 3600000 * 48).toISOString(),
      },
      {
        id: "usr-004",
        name: "Gate Scanner Unit 1",
        email: "gate1@clevawave.com",
        role: "Gate Staff",
        is_active: true,
        has_mfa: false,
        last_login_at: new Date(Date.now() - 7200000).toISOString(),
      },
    ],
  });
}
