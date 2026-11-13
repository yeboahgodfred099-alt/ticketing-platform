import { NextResponse } from "next/server";

export async function POST() {
  return NextResponse.json({
    data: {
      banner_url: "https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200",
    },
    message: "Banner uploaded successfully.",
  });
}
