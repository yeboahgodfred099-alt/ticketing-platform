import { NextResponse } from "next/server";

export async function POST() {
  return NextResponse.json({
    data: {
      status: "unpublished",
    },
    message: "Event unpublished successfully.",
  });
}
