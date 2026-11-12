"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { TicketType } from "@/types/event";
import { formatMoney } from "@/lib/utils";

interface TicketSelectorProps {
  eventSlug: string;
  ticketTypes: TicketType[];
}

export function TicketSelector({ eventSlug, ticketTypes }: TicketSelectorProps) {
  const router = useRouter();
  const [quantities, setQuantities] = useState<Record<string, number>>({});

  const handleQuantityChange = (ticketId: string, delta: number, maxAvailable: number, maxPerOrder: number | null) => {
    const current = quantities[ticketId] || 0;
    const limit = maxPerOrder ? Math.min(maxAvailable, maxPerOrder) : maxAvailable;
    const next = Math.max(0, Math.min(limit, current + delta));

    setQuantities((prev) => ({
      ...prev,
      [ticketId]: next,
    }));
  };

  const totalTickets = Object.values(quantities).reduce((sum, q) => sum + q, 0);

  const totalAmountMinor = ticketTypes.reduce((sum, tt) => {
    const q = quantities[tt.id] || 0;
    return sum + q * tt.price_minor;
  }, 0);

  const handleProceedToCheckout = () => {
    if (totalTickets === 0) return;

    // Encode selected items to checkout query / session
    const selectedItems = Object.entries(quantities)
      .filter(([, q]) => q > 0)
      .map(([id, q]) => `${id}:${q}`)
      .join(",");

    router.push(`/checkout/${eventSlug}?items=${encodeURIComponent(selectedItems)}`);
  };

  return (
    <div className="space-y-6">
      <div className="space-y-4">
        {ticketTypes.map((tt) => {
          const qty = quantities[tt.id] || 0;
          const isSoldOut = tt.available_quantity === 0;
          const maxOrder = tt.max_per_order || 10;

          return (
            <div
              key={tt.id}
              className={`p-5 rounded-xl border transition-all ${
                qty > 0
                  ? "bg-emerald-950/20 border-emerald-500/50"
                  : isSoldOut
                  ? "bg-slate-900/40 border-slate-800/40 opacity-60"
                  : "bg-slate-900 border-slate-800 hover:border-slate-700"
              } flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4`}
            >
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <h4 className="text-base font-bold text-white">{tt.name}</h4>
                  {isSoldOut ? (
                    <span className="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-red-950 text-red-400 border border-red-800">
                      Sold Out
                    </span>
                  ) : (
                    <span className="text-[10px] text-slate-400">
                      {tt.available_quantity} remaining
                    </span>
                  )}
                </div>

                {tt.description && (
                  <p className="text-xs text-slate-400 mt-1 leading-relaxed">
                    {tt.description}
                  </p>
                )}

                <div className="text-base font-extrabold text-white mt-2">
                  {formatMoney(tt.price_minor)}
                </div>
              </div>

              {/* Quantity Controls */}
              {!isSoldOut ? (
                <div className="flex items-center gap-3 self-end sm:self-center">
                  <button
                    type="button"
                    onClick={() => handleQuantityChange(tt.id, -1, tt.available_quantity, tt.max_per_order)}
                    disabled={qty === 0}
                    className="w-9 h-9 rounded-lg bg-slate-800 border border-slate-700 hover:bg-slate-700 disabled:opacity-30 disabled:cursor-not-allowed text-white text-lg font-bold flex items-center justify-center transition-colors"
                  >
                    -
                  </button>

                  <span className="w-8 text-center font-mono font-bold text-white text-base">
                    {qty}
                  </span>

                  <button
                    type="button"
                    onClick={() => handleQuantityChange(tt.id, 1, tt.available_quantity, tt.max_per_order)}
                    disabled={qty >= Math.min(tt.available_quantity, maxOrder)}
                    className="w-9 h-9 rounded-lg bg-slate-800 border border-slate-700 hover:bg-slate-700 disabled:opacity-30 disabled:cursor-not-allowed text-white text-lg font-bold flex items-center justify-center transition-colors"
                  >
                    +
                  </button>
                </div>
              ) : (
                <div className="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                  Unavailable
                </div>
              )}
            </div>
          );
        })}
      </div>

      {/* Floating / Sticky Order Summary */}
      {totalTickets > 0 && (
        <div className="p-6 rounded-2xl bg-gradient-to-r from-slate-900 to-slate-950 border border-emerald-500/40 shadow-2xl flex flex-col sm:flex-row justify-between items-center gap-4">
          <div>
            <div className="text-xs text-slate-400">
              Selected: <strong className="text-white">{totalTickets} {totalTickets === 1 ? "ticket" : "tickets"}</strong>
            </div>
            <div className="text-2xl font-black text-white mt-0.5">
              Total: <span className="text-emerald-400">{formatMoney(totalAmountMinor)}</span>
            </div>
            <div className="text-[11px] text-slate-400">
              Includes all fees • Pay with MTN, AirtelTigo, or Telecel
            </div>
          </div>

          <button
            type="button"
            onClick={handleProceedToCheckout}
            className="w-full sm:w-auto px-8 py-3.5 rounded-xl font-bold bg-emerald-500 hover:bg-emerald-600 text-slate-950 transition-colors shadow-lg shadow-emerald-500/20 text-sm"
          >
            Checkout ({formatMoney(totalAmountMinor)}) →
          </button>
        </div>
      )}
    </div>
  );
}

