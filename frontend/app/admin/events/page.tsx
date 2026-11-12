"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { apiClient } from "@/lib/api-client";
import { EventItem } from "@/types/event";
import { formatEventDate } from "@/lib/utils";

export default function AdminEventsPage() {
  const [events, setEvents] = useState<EventItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const [search, setSearch] = useState("");

  const fetchEvents = async () => {
    setLoading(true);
    try {
      const res = await apiClient<{ data: EventItem[] }>("/api/v1/admin/events");
      setEvents(res.data);
    } catch {
      // Keep empty or fallback
      setEvents([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchEvents();
  }, []);

  const filteredEvents = events.filter((ev) => {
    const matchesStatus = statusFilter === "all" || ev.status === statusFilter;
    const matchesSearch =
      ev.name.toLowerCase().includes(search.toLowerCase()) ||
      ev.slug.toLowerCase().includes(search.toLowerCase()) ||
      (ev.venue_name && ev.venue_name.toLowerCase().includes(search.toLowerCase()));
    return matchesStatus && matchesSearch;
  });

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "published":
        return (
          <span className="px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-950/80 text-emerald-400 border border-emerald-800">
            Published
          </span>
        );
      case "draft":
        return (
          <span className="px-2.5 py-1 rounded-md text-xs font-semibold bg-amber-950/80 text-amber-400 border border-amber-800">
            Draft
          </span>
        );
      case "cancelled":
        return (
          <span className="px-2.5 py-1 rounded-md text-xs font-semibold bg-red-950/80 text-red-400 border border-red-800">
            Cancelled
          </span>
        );
      case "unpublished":
        return (
          <span className="px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-800 text-slate-300 border border-slate-700">
            Unpublished
          </span>
        );
      case "archived":
        return (
          <span className="px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-900 text-slate-400 border border-slate-800">
            Archived
          </span>
        );
      default:
        return (
          <span className="px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-800 text-slate-400">
            {status}
          </span>
        );
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Events Management</h1>
          <p className="text-xs text-slate-400 mt-1">
            Create, configure, publish, and monitor your events and ticket tiers.
          </p>
        </div>

        <Link
          href="/admin/events/new"
          className="px-4 py-2.5 rounded-lg text-xs font-semibold bg-emerald-500 hover:bg-emerald-600 text-slate-950 transition-colors shadow-lg shadow-emerald-500/20 flex items-center gap-2"
        >
          <span>+</span> Create New Event
        </Link>
      </div>

      {/* Filter and Search Bar */}
      <div className="p-4 rounded-xl bg-slate-900 border border-slate-800 flex flex-col md:flex-row gap-4 justify-between items-center">
        {/* Status Pills */}
        <div className="flex flex-wrap gap-2 w-full md:w-auto">
          {["all", "published", "draft", "unpublished", "cancelled", "archived"].map((st) => (
            <button
              key={st}
              onClick={() => setStatusFilter(st)}
              className={`px-3 py-1.5 rounded-lg text-xs font-medium capitalize transition-colors ${
                statusFilter === st
                  ? "bg-emerald-500/10 text-emerald-400 border border-emerald-500/30"
                  : "text-slate-400 hover:text-white bg-slate-800/40 border border-transparent"
              }`}
            >
              {st}
            </button>
          ))}
        </div>

        {/* Search */}
        <div className="w-full md:w-64">
          <input
            type="text"
            placeholder="Search events..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full px-3.5 py-1.5 rounded-lg bg-slate-950 border border-slate-700 text-xs text-white placeholder-slate-400 focus:outline-none focus:border-emerald-500"
          />
        </div>
      </div>

      {/* Events Table */}
      <div className="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow">
        {loading ? (
          <div className="p-12 text-center text-slate-400 text-xs">
            <div className="inline-block animate-spin text-xl mb-2">⏳</div>
            <p>Loading events...</p>
          </div>
        ) : filteredEvents.length === 0 ? (
          <div className="p-12 text-center space-y-3">
            <div className="text-3xl text-slate-400">🎟️</div>
            <h3 className="text-sm font-bold text-white">No events found</h3>
            <p className="text-xs text-slate-400 max-w-sm mx-auto">
              {search || statusFilter !== "all"
                ? "No events match the selected filters."
                : "Get started by creating your first event to publish ticket sales."}
            </p>
            {statusFilter === "all" && !search && (
              <div className="pt-2">
                <Link
                  href="/admin/events/new"
                  className="px-4 py-2 rounded-lg text-xs font-semibold bg-emerald-500 hover:bg-emerald-600 text-slate-950 transition-colors inline-block"
                >
                  Create Event
                </Link>
              </div>
            )}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-950/60 border-b border-slate-800 text-slate-400 uppercase font-semibold">
                <tr>
                  <th className="px-6 py-3.5">Event</th>
                  <th className="px-6 py-3.5">Schedule</th>
                  <th className="px-6 py-3.5">Status</th>
                  <th className="px-6 py-3.5">Capacity / Sold</th>
                  <th className="px-6 py-3.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/80">
                {filteredEvents.map((ev) => (
                  <tr key={ev.id} className="hover:bg-slate-800/30 transition-colors">
                    <td className="px-6 py-4">
                      <div className="font-bold text-white text-sm">{ev.name}</div>
                      <div className="text-[11px] text-slate-400 flex items-center gap-1.5 mt-0.5">
                        <span>📍 {ev.venue_name || "TBA"}</span>
                        <span>•</span>
                        <span className="font-mono text-slate-400">/{ev.slug}</span>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="text-slate-200">
                        {formatEventDate(ev.starts_at, ev.timezone, false)}
                      </div>
                      <div className="text-[11px] text-slate-400 mt-0.5">
                        Timezone: {ev.timezone}
                      </div>
                    </td>
                    <td className="px-6 py-4">{getStatusBadge(ev.status)}</td>
                    <td className="px-6 py-4">
                      <div className="text-slate-200 font-medium">
                        {ev.total_sold ?? 0} / {ev.total_capacity ?? 0}
                      </div>
                      <div className="text-[11px] text-slate-400">
                        {ev.ticket_types?.length ?? 0} ticket categories
                      </div>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex items-center justify-end gap-2">
                        {ev.status === "published" && (
                          <Link
                            href={`/events/${ev.slug}`}
                            target="_blank"
                            className="px-2.5 py-1.5 rounded-md bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors text-[11px]"
                            title="View Public Page"
                          >
                            Public ↗
                          </Link>
                        )}
                        <Link
                          href={`/admin/events/${ev.id}`}
                          className="px-3 py-1.5 rounded-md bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 transition-colors text-[11px] font-semibold"
                        >
                          Manage →
                        </Link>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}

