"use client";

import { useEffect, useState, use } from "react";
import Link from "next/link";
import { apiClient, ApiError } from "@/lib/api-client";
import { RetrievedTicketsData, RetrievedTicket } from "@/types/ticket";
import { formatEventDate } from "@/lib/utils";

interface TicketPageProps {
  params: Promise<{ accessToken: string }>;
}

export default function TicketRetrievalPage({ params }: TicketPageProps) {
  const { accessToken } = use(params);

  const [data, setData] = useState<RetrievedTicketsData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!accessToken) return;

    let isMounted = true;
    setLoading(true);
    setError(null);

    apiClient<{ data: RetrievedTicketsData }>(`/api/v1/public/tickets/${accessToken}`)
      .then((res) => {
        if (isMounted) {
          setData(res.data);
          setLoading(false);
        }
      })
      .catch((err) => {
        if (isMounted) {
          if (err instanceof ApiError) {
            setError(err.message);
          } else {
            setError("Unable to retrieve ticket passes. Please check your access link.");
          }
          setLoading(false);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [accessToken]);

  const handlePrint = () => {
    if (typeof window !== "undefined") {
      window.print();
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-8">
        <div className="text-center space-y-3">
          <div className="text-3xl animate-spin">🎟️</div>
          <p className="text-xs text-slate-400">Loading your official tickets...</p>
        </div>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-8">
        <div className="max-w-md w-full p-8 rounded-2xl bg-slate-900 border border-slate-800 text-center space-y-4 shadow-xl">
          <div className="text-4xl">⚠️</div>
          <h2 className="text-xl font-bold text-white">Tickets Not Available</h2>
          <p className="text-xs text-slate-400 leading-relaxed">
            {error || "The ticket access link is invalid, expired, or the payment is still being confirmed."}
          </p>
          <Link
            href="/"
            className="px-5 py-2.5 rounded-lg text-xs font-semibold bg-emerald-500 text-slate-950 inline-block transition-colors"
          >
            ← Browse Events
          </Link>
        </div>
      </div>
    );
  }

  const { event, order, tickets } = data;

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col print:bg-white print:text-black">
      {/* Header (Hidden on print) */}
      <header className="border-b border-slate-800 bg-slate-900/80 backdrop-blur sticky top-0 z-50 print:hidden">
        <div className="max-w-4xl mx-auto px-4 h-16 flex items-center justify-between">
          <Link href="/" className="text-xl font-bold tracking-tight text-white">
            ClevaWave <span className="text-emerald-400">Tickets</span>
          </Link>
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={handlePrint}
              className="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 transition-colors flex items-center gap-1.5"
            >
              <span>🖨️</span> Print / Save PDF
            </button>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="flex-1 max-w-4xl mx-auto px-4 py-10 w-full space-y-8">
        {/* Event Header Banner */}
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 space-y-3 shadow-xl print:border-black print:bg-white">
          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-950/80 text-emerald-400 border border-emerald-800 print:hidden">
            <span>✓</span> Official Admission Passes
          </div>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-white tracking-tight print:text-black">
            {event.name}
          </h1>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-slate-300 pt-2 print:text-black">
            <div>
              <span className="text-slate-400 font-medium print:text-gray-600">Date & Time: </span>
              <span className="font-semibold">{formatEventDate(event.starts_at, event.timezone, true)}</span>
            </div>
            <div>
              <span className="text-slate-400 font-medium print:text-gray-600">Venue: </span>
              <span className="font-semibold">{event.venue_name || "TBA"}</span>
              {event.venue_address && <span className="text-slate-400 print:text-gray-600"> ({event.venue_address})</span>}
            </div>
            <div>
              <span className="text-slate-400 font-medium print:text-gray-600">Order Ref: </span>
              <span className="font-mono">{order.order_number}</span>
            </div>
            <div>
              <span className="text-slate-400 font-medium print:text-gray-600">Issued To: </span>
              <span className="font-semibold">{order.customer_name}</span>
            </div>
          </div>
        </div>

        {/* Admission Instruction Banner (Hidden on print) */}
        <div className="p-4 rounded-xl bg-slate-900/60 border border-slate-800 flex items-start gap-3 text-xs text-slate-300 print:hidden">
          <span className="text-xl">📲</span>
          <div className="space-y-0.5">
            <span className="font-bold text-white">How to Use Your Ticket at the Gate</span>
            <p className="text-slate-400">
              Present the scannable QR code on your phone screen at the venue entrance. Staff will scan the code for instant admission. No printing required.
            </p>
          </div>
        </div>

        {/* Tickets Grid */}
        <div className="space-y-6">
          <h2 className="text-lg font-bold text-white print:text-black">
            Your Tickets ({tickets.length})
          </h2>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {tickets.map((ticket: RetrievedTicket) => (
              <div
                key={ticket.id}
                className="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-2xl flex flex-col justify-between print:bg-white print:border-black print:break-inside-avoid"
              >
                {/* Ticket Top: Category & Number */}
                <div className="p-6 border-b border-slate-800/80 flex justify-between items-start print:border-gray-300">
                  <div>
                    <span className="text-[10px] uppercase font-bold tracking-wider text-emerald-400 print:text-black">
                      {ticket.category_name}
                    </span>
                    <h3 className="text-lg font-extrabold text-white mt-0.5 print:text-black">
                      {ticket.holder_name}
                    </h3>
                    <p className="text-[11px] font-mono text-slate-400 mt-1 print:text-gray-600">
                      #{ticket.ticket_number}
                    </p>
                  </div>

                  <span
                    className={`px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider ${
                      ticket.status === "active"
                        ? "bg-emerald-950/80 text-emerald-400 border border-emerald-800"
                        : "bg-slate-800 text-slate-400"
                    } print:border print:border-black print:text-black`}
                  >
                    {ticket.status === "active" ? "Valid" : ticket.status}
                  </span>
                </div>

                {/* Ticket Middle: High-Contrast QR Code */}
                <div className="p-6 flex flex-col items-center justify-center bg-slate-950/60 print:bg-white">
                  <div className="p-3 bg-white rounded-xl shadow-md">
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={ticket.qr_code_data_uri}
                      alt={`QR Code for ${ticket.ticket_number}`}
                      className="w-48 h-48 object-contain"
                    />
                  </div>
                  <span className="text-[10px] font-mono text-slate-400 mt-3 print:text-gray-600">
                    Scan for Gate Admission
                  </span>
                </div>

                {/* Ticket Bottom: Event Info */}
                <div className="p-4 bg-slate-950 border-t border-slate-800/80 text-center text-[11px] text-slate-400 print:bg-white print:border-gray-300 print:text-black">
                  <span>{event.name} • </span>
                  <span>{formatEventDate(event.starts_at, event.timezone, false)}</span>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Bookmark Advice (Hidden on print) */}
        <div className="text-center pt-6 pb-12 text-xs text-slate-400 print:hidden space-y-2">
          <p>🔖 Bookmark this page or save the link sent to <strong className="text-slate-300">{order.customer_email}</strong> to access your tickets anytime.</p>
        </div>
      </main>
    </div>
  );
}
