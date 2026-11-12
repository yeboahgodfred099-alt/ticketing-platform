"use client";

import { useEffect, useState, Suspense } from "react";
import { useParams, useRouter, useSearchParams } from "next/navigation";
import Link from "next/link";
import { apiClient, ApiError } from "@/lib/api-client";
import { EventItem, TicketType } from "@/types/event";
import { OrderData } from "@/types/order";
import { formatEventDate, formatMoney } from "@/lib/utils";
import { GHANA_MOBILE_MONEY_NETWORKS } from "@/lib/constants";

function CheckoutContent() {
  const params = useParams();
  const searchParams = useSearchParams();
  const router = useRouter();

  const eventSlug = params.eventSlug as string;
  const initialItemsParam = searchParams.get("items") || "";

  const [event, setEvent] = useState<EventItem | null>(null);
  const [quantities, setQuantities] = useState<Record<string, number>>({});
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  // Customer form fields
  const [customerName, setCustomerName] = useState("");
  const [customerEmail, setCustomerEmail] = useState("");
  const [customerPhone, setCustomerPhone] = useState("");

  useEffect(() => {
    if (!eventSlug) return;

    let isMounted = true;
    setLoading(true);

    apiClient<{ data: EventItem }>(`/api/v1/public/events/${eventSlug}`)
      .then((res) => {
        if (!isMounted) return;
        const ev = res.data;
        setEvent(ev);

        // Parse initial items from query param (format: id:qty,id2:qty2)
        const initialMap: Record<string, number> = {};
        if (initialItemsParam) {
          const pairs = initialItemsParam.split(",");
          for (const pair of pairs) {
            const [id, countStr] = pair.split(":");
            const count = parseInt(countStr, 10);
            if (id && !isNaN(count) && count > 0) {
              initialMap[id] = count;
            }
          }
        }

        // If no query param provided, select 1 of the first available ticket category
        if (Object.keys(initialMap).length === 0 && ev.ticket_types && ev.ticket_types.length > 0) {
          const firstAvailable = ev.ticket_types.find((t) => t.available_quantity > 0);
          if (firstAvailable) {
            initialMap[firstAvailable.id] = 1;
          }
        }

        setQuantities(initialMap);
        setLoading(false);
      })
      .catch((err) => {
        if (isMounted) {
          setError(err.message || "Failed to load event for checkout.");
          setLoading(false);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [eventSlug, initialItemsParam]);

  const ticketTypes = event?.ticket_types || [];

  const handleQuantityDelta = (ticketId: string, delta: number, available: number, maxPerOrder: number | null) => {
    const current = quantities[ticketId] || 0;
    const limit = maxPerOrder ? Math.min(available, maxPerOrder) : available;
    const next = Math.max(0, Math.min(limit, current + delta));

    setQuantities((prev) => ({
      ...prev,
      [ticketId]: next,
    }));
  };

  const totalTickets = Object.values(quantities).reduce((sum, q) => sum + q, 0);

  const totalMinor = ticketTypes.reduce((sum, tt) => {
    const q = quantities[tt.id] || 0;
    return sum + q * tt.price_minor;
  }, 0);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!event || totalTickets === 0) return;

    setSubmitting(true);
    setError(null);
    setFieldErrors({});

    try {
      const itemsPayload = Object.entries(quantities)
        .filter(([, q]) => q > 0)
        .map(([ticket_type_id, quantity]) => ({
          ticket_type_id,
          quantity,
        }));

      const res = await apiClient<{ data: OrderData }>("/api/v1/public/checkout", {
        method: "POST",
        body: JSON.stringify({
          event_slug: event.slug,
          customer_name: customerName.trim(),
          customer_email: customerEmail.trim(),
          customer_phone: customerPhone.trim(),
          items: itemsPayload,
        }),
      });

      const order = res.data;
      const rawToken = order.access_token;

      // Store token securely in sessionStorage for retrieval
      if (rawToken && typeof window !== "undefined") {
        sessionStorage.setItem(`order_token_${order.order_number}`, rawToken);
      }

      // Navigate to payment page (Phase 5)
      router.push(`/payment/${order.order_number}?token=${rawToken}`);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
        if (err.errors) {
          setFieldErrors(err.errors);
        }
      } else {
        setError("An unexpected error occurred during checkout. Please try again.");
      }
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <div className="max-w-4xl mx-auto px-4 py-16 animate-pulse space-y-6">
        <div className="h-8 bg-slate-900 rounded w-1/3" />
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div className="md:col-span-2 h-96 bg-slate-900 rounded-2xl" />
          <div className="h-80 bg-slate-900 rounded-2xl" />
        </div>
      </div>
    );
  }

  if (error && !event) {
    return (
      <div className="max-w-xl mx-auto px-4 py-24 text-center">
        <div className="text-4xl mb-4">⚠️</div>
        <h2 className="text-xl font-bold text-white mb-2">Unable to Start Checkout</h2>
        <p className="text-xs text-slate-400 mb-6">{error}</p>
        <Link
          href="/"
          className="px-5 py-2.5 rounded-lg text-xs font-semibold bg-emerald-500 text-slate-950 inline-block"
        >
          ← Back to Events
        </Link>
      </div>
    );
  }

  if (!event) return null;

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
      {/* Header */}
      <header className="border-b border-slate-800 bg-slate-900/80 backdrop-blur sticky top-0 z-50">
        <div className="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between">
          <Link href="/" className="text-xl font-bold tracking-tight text-white">
            ClevaWave <span className="text-emerald-400">Tickets</span>
          </Link>
          <div className="flex items-center gap-2 text-xs text-slate-400">
            <span>🔒 Secure Anonymous Checkout</span>
          </div>
        </div>
      </header>

      <main className="flex-1 max-w-5xl mx-auto px-4 py-10 w-full space-y-8">
        <div>
          <div className="flex items-center gap-2 text-xs text-slate-400 mb-1">
            <Link href={`/events/${event.slug}`} className="hover:text-white transition-colors">
              ← {event.name}
            </Link>
            <span>/</span>
            <span>Checkout</span>
          </div>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
            Complete Your Ticket Order
          </h1>
          <p className="text-xs text-slate-400 mt-1">
            No password required. Enter your details to receive your tickets and initiate Mobile Money payment.
          </p>
        </div>

        {error && (
          <div className="p-4 rounded-xl bg-red-950/60 border border-red-800 text-xs text-red-300">
            {error}
          </div>
        )}

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Left 2 Cols: Guest Form & Ticket Summary */}
          <div className="lg:col-span-2 space-y-6">
            {/* Step 1: Ticket Selection Confirmation */}
            <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
              <h3 className="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                <span className="w-5 h-5 rounded-full bg-emerald-500/20 text-emerald-400 text-xs flex items-center justify-center font-bold">
                  1
                </span>
                Review Ticket Selection
              </h3>

              <div className="divide-y divide-slate-800/80">
                {ticketTypes.map((tt: TicketType) => {
                  const qty = quantities[tt.id] || 0;
                  const isSoldOut = tt.available_quantity === 0;

                  return (
                    <div key={tt.id} className="py-3.5 flex justify-between items-center gap-4">
                      <div>
                        <div className="font-semibold text-white text-sm">{tt.name}</div>
                        <div className="text-xs text-emerald-400 font-medium">
                          {formatMoney(tt.price_minor)} each
                        </div>
                      </div>

                      {/* Quantity buttons */}
                      <div className="flex items-center gap-2.5">
                        <button
                          type="button"
                          onClick={() => handleQuantityDelta(tt.id, -1, tt.available_quantity, tt.max_per_order)}
                          disabled={qty === 0}
                          className="w-7 h-7 rounded bg-slate-800 hover:bg-slate-700 disabled:opacity-30 disabled:cursor-not-allowed text-white text-sm font-bold flex items-center justify-center"
                        >
                          -
                        </button>
                        <span className="w-8 text-center text-sm font-bold text-white">
                          {qty}
                        </span>
                        <button
                          type="button"
                          onClick={() => handleQuantityDelta(tt.id, 1, tt.available_quantity, tt.max_per_order)}
                          disabled={isSoldOut || qty >= (tt.max_per_order || tt.available_quantity)}
                          className="w-7 h-7 rounded bg-slate-800 hover:bg-slate-700 disabled:opacity-30 disabled:cursor-not-allowed text-white text-sm font-bold flex items-center justify-center"
                        >
                          +
                        </button>
                      </div>
                    </div>
                  );
                })}
              </div>

              {totalTickets === 0 && (
                <div className="p-3 rounded-lg bg-amber-950/40 border border-amber-800/60 text-xs text-amber-300">
                  Please select at least 1 ticket to continue.
                </div>
              )}
            </div>

            {/* Step 2: Contact Information Form */}
            <form id="checkout-form" onSubmit={handleSubmit} className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-5">
              <h3 className="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                <span className="w-5 h-5 rounded-full bg-emerald-500/20 text-emerald-400 text-xs flex items-center justify-center font-bold">
                  2
                </span>
                Attendee & Contact Information
              </h3>

              <div className="space-y-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-300 mb-1.5">
                    Full Name <span className="text-emerald-400">*</span>
                  </label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Kwame Mensah"
                    value={customerName}
                    onChange={(e) => setCustomerName(e.target.value)}
                    className="w-full px-4 py-2.5 rounded-lg bg-slate-950 border border-slate-700 text-sm text-white focus:outline-none focus:border-emerald-500"
                  />
                  {fieldErrors.customer_name && (
                    <p className="text-xs text-red-400 mt-1">{fieldErrors.customer_name[0]}</p>
                  )}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-300 mb-1.5">
                    Email Address <span className="text-emerald-400">*</span>
                  </label>
                  <input
                    type="email"
                    required
                    placeholder="kwame@example.com"
                    value={customerEmail}
                    onChange={(e) => setCustomerEmail(e.target.value)}
                    className="w-full px-4 py-2.5 rounded-lg bg-slate-950 border border-slate-700 text-sm text-white focus:outline-none focus:border-emerald-500"
                  />
                  <p className="text-[11px] text-slate-400 mt-1">
                    Your cryptographic QR tickets and receipt will be sent to this email.
                  </p>
                  {fieldErrors.customer_email && (
                    <p className="text-xs text-red-400 mt-1">{fieldErrors.customer_email[0]}</p>
                  )}
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-300 mb-1.5">
                    Mobile Money Phone Number <span className="text-emerald-400">*</span>
                  </label>
                  <input
                    type="tel"
                    required
                    placeholder="024XXXXXXX or +23324XXXXXXX"
                    value={customerPhone}
                    onChange={(e) => setCustomerPhone(e.target.value)}
                    className="w-full px-4 py-2.5 rounded-lg bg-slate-950 border border-slate-700 text-sm text-white font-mono focus:outline-none focus:border-emerald-500"
                  />
                  <div className="flex flex-wrap gap-2 mt-2">
                    {GHANA_MOBILE_MONEY_NETWORKS.map((net) => (
                      <span
                        key={net.code}
                        className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-950 border border-slate-800 text-[10px] text-slate-300"
                      >
                        <span className={`w-1.5 h-1.5 rounded-full ${net.badgeColor}`} />
                        {net.shortName}
                      </span>
                    ))}
                  </div>
                  {fieldErrors.customer_phone && (
                    <p className="text-xs text-red-400 mt-1">{fieldErrors.customer_phone[0]}</p>
                  )}
                </div>
              </div>
            </form>
          </div>

          {/* Right Col: Order Summary & Lock CTA */}
          <div className="lg:col-span-1">
            <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 sticky top-24 space-y-6 shadow-xl">
              <div>
                <h3 className="text-base font-bold text-white">Order Summary</h3>
                <div className="text-xs text-slate-400 mt-1">
                  {event.name}
                </div>
                <div className="text-[11px] text-emerald-400 mt-0.5">
                  📅 {formatEventDate(event.starts_at, event.timezone, false)}
                </div>
              </div>

              {/* Cost breakdown */}
              <div className="space-y-3 pt-3 border-t border-slate-800 text-xs">
                <div className="flex justify-between text-slate-300">
                  <span>Tickets ({totalTickets})</span>
                  <span>{formatMoney(totalMinor)}</span>
                </div>
                <div className="flex justify-between text-slate-400">
                  <span>Processing Fees</span>
                  <span className="text-emerald-400 font-semibold">GH₵ 0.00 (Free)</span>
                </div>
                <div className="flex justify-between text-base font-extrabold text-white pt-3 border-t border-slate-800">
                  <span>Total Amount</span>
                  <span className="text-emerald-400">{formatMoney(totalMinor)}</span>
                </div>
              </div>

              {/* Reservation Guarantee Note */}
              <div className="p-3.5 rounded-xl bg-slate-950/70 border border-slate-800/80 space-y-1.5 text-[11px] text-slate-400">
                <div className="flex items-center gap-1.5 text-white font-semibold">
                  <span>⏱️</span> 15-Minute Reservation Hold
                </div>
                <p>
                  Once you proceed, your tickets are locked exclusively for you for 15 minutes while you approve the payment on your phone.
                </p>
              </div>

              <button
                type="submit"
                form="checkout-form"
                disabled={submitting || totalTickets === 0}
                className="w-full py-3.5 rounded-xl font-bold bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed text-slate-950 text-sm transition-colors shadow-lg shadow-emerald-500/20"
              >
                {submitting ? "Reserving Tickets..." : "Continue to Mobile Money →"}
              </button>

              <div className="text-[10px] text-center text-slate-400 leading-tight">
                By continuing, you agree that your contact information will be used for ticket delivery and event updates.
              </div>
            </div>
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="border-t border-slate-900 bg-slate-950 py-6 text-center text-xs text-slate-400 mt-16">
        <p>© 2026 ClevaWave Tickets. Zero Registration • Instant MoMo Issuance</p>
      </footer>
    </div>
  );
}

export default function CheckoutPage() {
  return (
    <Suspense
      fallback={
        <div className="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-8">
          <div className="text-center space-y-2">
            <div className="text-2xl animate-spin">⏳</div>
            <p className="text-xs text-slate-400">Loading checkout...</p>
          </div>
        </div>
      }
    >
      <CheckoutContent />
    </Suspense>
  );
}
