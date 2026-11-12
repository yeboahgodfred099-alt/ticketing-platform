import { EventItem } from "@/types/event";
import { EventCard } from "./EventCard";

interface EventGridProps {
  events: EventItem[];
  loading?: boolean;
}

export function EventGrid({ events, loading = false }: EventGridProps) {
  if (loading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {[1, 2, 3].map((i) => (
          <div
            key={i}
            className="rounded-2xl bg-slate-900 border border-slate-800 p-6 animate-pulse h-80 flex flex-col justify-between"
          >
            <div className="aspect-[16/9] w-full bg-slate-800 rounded-lg" />
            <div className="space-y-3 mt-4">
              <div className="h-4 bg-slate-800 rounded w-1/3" />
              <div className="h-6 bg-slate-800 rounded w-3/4" />
              <div className="h-4 bg-slate-800 rounded w-1/2" />
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (events.length === 0) {
    return (
      <div className="p-12 text-center rounded-2xl bg-slate-900/60 border border-slate-800">
        <div className="text-4xl mb-3">🎟️</div>
        <h3 className="text-lg font-bold text-white">No Upcoming Events</h3>
        <p className="text-xs text-slate-400 mt-1">
          Check back soon for new events or subscribe to event alerts.
        </p>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      {events.map((event) => (
        <EventCard key={event.id} event={event} />
      ))}
    </div>
  );
}

