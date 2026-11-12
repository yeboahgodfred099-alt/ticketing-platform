import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "ClevaWave Ticketing — Events in Ghana",
  description: "Secure, instant event ticketing with Paystack Ghana Mobile Money. No account required.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body className="antialiased font-sans bg-background text-foreground min-h-screen">
        {children}
      </body>
    </html>
  );
}
