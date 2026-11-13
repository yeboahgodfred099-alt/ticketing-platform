"use client";

import { useEffect, useState } from "react";
import { apiClient } from "@/lib/api-client";
import { formatEventDate } from "@/lib/utils";

interface AttendeeRecord {
  id: string;
  ticket_number: string;
  event_name: string;
  tier_name: string;
  attendee_name: string;
  attendee_phone: string;
  status: "active" | "used" | "cancelled";
  checked_in_at: string | null;
}

export default function AdminAttendeesPage() {
  const [attendees, setAttendees] = useState<AttendeeRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");

  useEffect(() => {
    apiClient<{ data: AttendeeRecord[] }>("/admin/attendees")
      .then((res) => setAttendees(res.data || []))
      .catch(() => setAttendees([]))
      .finally(() => setLoading(false));
  }, []);

  const filteredAttendees = attendees.filter((a) => {
    return (
      a.ticket_number.toLowerCase().includes(search.toLowerCase()) ||
      a.attendee_name.toLowerCase().includes(search.toLowerCase()) ||
      a.tier_name.toLowerCase().includes(search.toLowerCase())
    );
  });

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-6 border-b border-slate-800">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Attendees & Tickets</h1>
          <p className="text-xs text-slate-400 mt-1">
            Registered ticket holders, gate admission statuses, and check-in logs.
          </p>
        </div>
      </div>

      {/* Search Input */}
      <div className="p-4 rounded-xl bg-slate-900 border border-slate-800">
        <input
          type="text"
          placeholder="Search attendee name, ticket number (TCK-...), or tier..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
        />
      </div>

      {/* Attendees Table */}
      <div className="rounded-xl bg-slate-900 border border-slate-800 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs text-slate-300">
            <thead className="text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-800 bg-slate-950/60">
              <tr>
                <th className="py-3 px-4">Ticket Number</th>
                <th className="py-3 px-4">Attendee</th>
                <th className="py-3 px-4">Tier</th>
                <th className="py-3 px-4">Event</th>
                <th className="py-3 px-4">Gate Status</th>
                <th className="py-3 px-4">Check-In Time</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800">
              {loading ? (
                <tr>
                  <td colSpan={6} className="py-8 text-center text-slate-500">
                    Loading attendees...
                  </td>
                </tr>
              ) : filteredAttendees.length === 0 ? (
                <tr>
                  <td colSpan={6} className="py-8 text-center text-slate-500">
                    No matching attendees found.
                  </td>
                </tr>
              ) : (
                filteredAttendees.map((att) => (
                  <tr key={att.id} className="hover:bg-slate-800/40">
                    <td className="py-3 px-4 font-mono font-semibold text-white">
                      {att.ticket_number}
                    </td>
                    <td className="py-3 px-4">
                      <div className="font-semibold text-white">{att.attendee_name}</div>
                      <div className="text-[11px] text-slate-400">{att.attendee_phone}</div>
                    </td>
                    <td className="py-3 px-4">
                      <span className="inline-block px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        {att.tier_name}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-slate-300">{att.event_name}</td>
                    <td className="py-3 px-4">
                      {att.status === "used" ? (
                        <span className="inline-block px-2 py-0.5 rounded text-[11px] font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/30">
                          ✓ ADMITTED
                        </span>
                      ) : (
                        <span className="inline-block px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-800 text-slate-400 border border-slate-700">
                          NOT CHECKED IN
                        </span>
                      )}
                    </td>
                    <td className="py-3 px-4 text-slate-400 whitespace-nowrap">
                      {att.checked_in_at ? formatEventDate(att.checked_in_at) : "—"}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
