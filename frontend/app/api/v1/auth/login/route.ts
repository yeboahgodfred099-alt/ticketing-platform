import { NextResponse } from "next/server";

export async function POST() {
  return NextResponse.json({
    requires_mfa: false,
    message: "Authenticated successfully.",
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
        },
      ],
    },
  });
}

