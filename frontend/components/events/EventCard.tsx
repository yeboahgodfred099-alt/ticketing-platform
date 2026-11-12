import Link from "next/link";
import { EventItem } from "@/types/event";
import { formatEventDate, formatMoney } from "@/lib/utils";

interface EventCardProps {
  event: EventItem;
}

export function EventCard({ event }: EventCardProps) {
  const isSoldOut = event.is_sold_out;

  return (
    <div className="group rounded-2xl bg-slate-900 border border-slate-800 hover:border-slate-700 transition-all duration-300 flex flex-col overflow-hidden hover:shadow-xl hover:shadow-emerald-950/20">
      {/* Banner / Image Placeholder */}
      <div className="aspect-[16/9] w-full bg-slate-950 relative overflow-hidden flex items-center justify-center">
        {event.banner_url ? (
          /* eslint-disable-next-line @next/next/no-img-element */
          <img
            src={event.banner_url}
            alt={event.name}
            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
          />
        ) : (
          <div className="text-4xl text-slate-800 font-bold tracking-tight">
            🎟️
          </div>
        )}

        {/* Status badges */}
        <div className="absolute top-3 right-3 flex gap-2">
          {isSoldOut ? (
            <span className="px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider bg-red-950/90 text-red-400 border border-red-800">
              Sold Out
            </span>
          ) : (
            <span className="px-2.5 py-1 rounded-md text-[11px] font-semibold bg-emerald-950/90 text-emerald-400 border border-emerald-800">
              Available
            </span>
          )}
        </div>
      </div>

      {/* Content */}
      <div className="p-5 flex-1 flex flex-col justify-between">
        <div>
          <div className="text-xs font-semibold text-emerald-400 mb-1 flex items-center gap-1.5">
            <span>📅</span>
            {formatEventDate(event.starts_at, event.timezone, false)}
          </div>

          <h3 className="text-lg font-bold text-white group-hover:text-emerald-400 transition-colors line-clamp-1">
            {event.name}
          </h3>

          <p className="text-xs text-slate-400 mt-1 flex items-center gap-1 line-clamp-1">
            <span>📍</span>
            {event.venue_name || "Accra, Ghana"}
          </p>

          {event.description && (
            <p className="text-xs text-slate-400 mt-3 line-clamp-2 leading-relaxed">
              {event.description}
            </p>
          )}
        </div>

        {/* Footer / Pricing */}
        <div className="mt-5 pt-4 border-t border-slate-800/80 flex items-center justify-between">
          <div>
            <div className="text-[10px] uppercase font-semibold text-slate-400">Tickets From</div>
            <div className="text-base font-bold text-white">
              {event.lowest_price_minor !== undefined && event.lowest_price_minor > 0
                ? formatMoney(event.lowest_price_minor)
                : "Free"}
            </div>
          </div>

          <Link
            href={`/events/${event.slug}`}
            className="px-4 py-2 rounded-lg text-xs font-semibold bg-emerald-500 hover:bg-emerald-600 text-slate-950 transition-colors"
          >
            {isSoldOut ? "View Details" : "Get Tickets →"}
          </Link>
        </div>
      </div>
    </div>
  );
}

