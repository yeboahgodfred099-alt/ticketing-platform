"use client";

import { useEffect, useState } from "react";
import { useParams } from "next/navigation";
import Link from "next/link";
import { apiClient, ApiError } from "@/lib/api-client";
import { EventItem } from "@/types/event";
import { formatEventDate } from "@/lib/utils";
import { TicketSelector } from "@/components/events/TicketSelector";

export default function EventDetailPage() {
  const params = useParams();
  const slug = params.slug as string;

  const [event, setEvent] = useState<EventItem | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!slug) return;

    let isMounted = true;
    setLoading(true);
    setError(null);

    apiClient<{ data: EventItem }>(`/api/v1/public/events/${slug}`)
      .then((res) => {
        if (isMounted) {
          setEvent(res.data);
          setLoading(false);
        }
      })
      .catch((err) => {
        if (isMounted) {
          if (err instanceof ApiError && err.status === 404) {
            setError("Event not found or is currently not published.");
          } else {
            setError(err.message || "Failed to load event details.");
          }
          setLoading(false);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [slug]);

  if (loading) {
    return (
      <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
        <header className="border-b border-slate-800 bg-slate-900/80 backdrop-blur sticky top-0 z-50">
          <div className="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between">
            <Link href="/" className="text-xl font-bold tracking-tight text-white">
              ClevaWave <span className="text-emerald-400">Tickets</span>
            </Link>
          </div>
        </header>

        <main className="flex-1 max-w-5xl mx-auto px-4 py-12 w-full animate-pulse space-y-8">
          <div className="h-64 sm:h-80 bg-slate-900 rounded-2xl w-full" />
          <div className="space-y-4">
            <div className="h-8 bg-slate-900 rounded-lg w-2/3" />
            <div className="h-4 bg-slate-900 rounded w-1/3" />
            <div className="h-20 bg-slate-900 rounded-xl w-full" />
          </div>
        </main>
      </div>
    );
  }

  if (error || !event) {
    return (
      <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
        <header className="border-b border-slate-800 bg-slate-900/80 backdrop-blur sticky top-0 z-50">
          <div className="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between">
            <Link href="/" className="text-xl font-bold tracking-tight text-white">
              ClevaWave <span className="text-emerald-400">Tickets</span>
            </Link>
          </div>
        </header>

        <main className="flex-1 max-w-xl mx-auto px-4 py-24 text-center flex flex-col items-center justify-center">
          <div className="w-16 h-16 rounded-full bg-red-950/50 border border-red-800/80 flex items-center justify-center text-2xl mb-4">
            ⚠️
          </div>
          <h2 className="text-2xl font-bold text-white mb-2">Event Not Available</h2>
          <p className="text-sm text-slate-400 mb-8 max-w-md">
            {error || "The event you are looking for is unavailable, unpublished, or does not exist."}
          </p>
          <Link
            href="/"
            className="px-6 py-2.5 rounded-lg text-xs font-semibold bg-emerald-500 hover:bg-emerald-600 text-slate-950 transition-colors"
          >
            ← Back to All Events
          </Link>
        </main>
      </div>
    );
  }

  const isSoldOut = event.is_sold_out;
  const ticketTypes = event.ticket_types || [];

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
      {/* Navigation */}
      <header className="border-b border-slate-800 bg-slate-900/80 backdrop-blur sticky top-0 z-50">
        <div className="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between">
          <Link href="/" className="text-xl font-bold tracking-tight text-white">
            ClevaWave <span className="text-emerald-400">Tickets</span>
          </Link>
          <Link
            href="/"
            className="text-xs font-semibold text-slate-400 hover:text-white transition-colors flex items-center gap-1"
          >
            ← All Events
          </Link>
        </div>
      </header>

      {/* Main Content */}
      <main className="flex-1 max-w-5xl mx-auto px-4 py-10 w-full space-y-10">
        {/* Banner Section */}
        <div className="rounded-2xl overflow-hidden border border-slate-800 bg-slate-900 shadow-xl relative aspect-[21/9] sm:aspect-[24/9] flex items-center justify-center">
          {event.banner_url ? (
            /* eslint-disable-next-line @next/next/no-img-element */
            <img
              src={event.banner_url}
              alt={event.name}
              className="w-full h-full object-cover"
            />
          ) : (
            <div className="text-6xl text-slate-800">🎟️</div>
          )}

          <div className="absolute top-4 right-4 flex gap-2">
            {isSoldOut ? (
              <span className="px-3 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-red-950/95 text-red-400 border border-red-800 shadow">
                Sold Out
              </span>
            ) : (
              <span className="px-3 py-1 rounded-md text-xs font-semibold bg-emerald-950/95 text-emerald-400 border border-emerald-800 shadow">
                Tickets Available
              </span>
            )}
          </div>
        </div>

        {/* Event Header & Meta */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div className="lg:col-span-2 space-y-6">
            <div>
              <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-950/60 border border-emerald-800/80 text-emerald-400 mb-3">
                <span>📍</span>
                {event.venue_name || "Accra, Ghana"}
              </div>
              <h1 className="text-3xl sm:text-4xl font-extrabold text-white tracking-tight leading-tight">
                {event.name}
              </h1>
            </div>

            {/* Event Schedule & Location Card */}
            <div className="p-5 rounded-xl bg-slate-900 border border-slate-800 grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1">
                <div className="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                  Date & Time (Ghana Time)
                </div>
                <div className="text-sm font-bold text-white">
                  {formatEventDate(event.starts_at, event.timezone, true)}
                </div>
                <div className="text-xs text-slate-400">
                  Ends: {formatEventDate(event.ends_at, event.timezone, true)}
                </div>
              </div>

              <div className="space-y-1">
                <div className="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                  Location & Venue
                </div>
                <div className="text-sm font-bold text-white">
                  {event.venue_name || "Venue to be announced"}
                </div>
                {event.venue_address && (
                  <div className="text-xs text-slate-400">{event.venue_address}</div>
                )}
              </div>
            </div>

            {/* Description */}
            {event.description && (
              <div className="space-y-3">
                <h3 className="text-lg font-bold text-white">About This Event</h3>
                <div className="text-sm text-slate-300 leading-relaxed whitespace-pre-line bg-slate-900/40 p-6 rounded-xl border border-slate-800/60">
                  {event.description}
                </div>
              </div>
            )}
          </div>

          {/* Right Column: Ticket Selection Box */}
          <div className="lg:col-span-1">
            <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 sticky top-24 shadow-2xl space-y-6">
              <div>
                <h2 className="text-xl font-bold text-white">Select Tickets</h2>
                <p className="text-xs text-slate-400 mt-1">
                  Pay securely with MTN, Telecel, or AT Mobile Money.
                </p>
              </div>

              {ticketTypes.length === 0 ? (
                <div className="p-6 rounded-xl bg-slate-950/60 border border-slate-800 text-center">
                  <p className="text-xs text-slate-400">No ticket types are currently active.</p>
                </div>
              ) : (
                <TicketSelector eventSlug={event.slug} ticketTypes={ticketTypes} />
              )}
            </div>
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="border-t border-slate-900 bg-slate-950 py-8 text-center text-xs text-slate-400 mt-16">
        <p>© 2026 ClevaWave Tickets. Built for Ghana Mobile Money.</p>
      </footer>
    </div>
  );
}

