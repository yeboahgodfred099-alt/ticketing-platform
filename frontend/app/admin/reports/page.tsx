"use client";

import { useEffect, useState, useCallback } from "react";
import { apiClient } from "@/lib/api-client";
import { formatMoney } from "@/lib/utils";
import { ReportOverview } from "@/types/report";
import { EventItem } from "@/types/event";
import { API_BASE_URL } from "@/lib/constants";

export default function AdminReportsPage() {
  const [overview, setOverview] = useState<ReportOverview | null>(null);
  const [events, setEvents] = useState<EventItem[]>([]);
  const [selectedEventId, setSelectedEventId] = useState<string>("");
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  const fetchEvents = useCallback(async () => {
    try {
      const res = await apiClient<{ data: EventItem[] }>("/admin/events");
      setEvents(res.data || []);
    } catch {
      // Non-fatal if events fail to load
    }
  }, []);

  const fetchReports = useCallback(async (eventId?: string) => {
    setLoading(true);
    setError(null);
    try {
      const query = eventId ? `?event_id=${eventId}` : "";
      const res = await apiClient<{ data: ReportOverview }>(`/admin/reports/overview${query}`);
      setOverview(res.data);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Failed to load financial report.";
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchEvents();
    fetchReports();
  }, [fetchEvents, fetchReports]);

  const handleEventFilterChange = (eventId: string) => {
    setSelectedEventId(eventId);
    fetchReports(eventId);
  };

  const getExportUrl = (type: "orders" | "attendees") => {
    const params = new URLSearchParams();
    if (selectedEventId) {
      params.append("event_id", selectedEventId);
    }
    const query = params.toString() ? `?${params.toString()}` : "";
    return `${API_BASE_URL}/admin/reports/${type}/export${query}`;
  };

  const downloadCsv = async (type: "orders" | "attendees") => {
    try {
      const url = getExportUrl(type);
      const res = await fetch(url, {
        credentials: "include",
        headers: {
          Accept: "text/csv",
        },
      });

      if (!res.ok) {
        throw new Error(`Export failed with status ${res.status}`);
      }

      const blob = await res.blob();
      const downloadUrl = window.URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = downloadUrl;
      a.download = `${type}_export_${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(downloadUrl);
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to download CSV export.");
    }
  };

  return (
    <div className="space-y-8">
      {/* Header & Controls */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-6 border-b border-slate-800">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Financial & Operations Reports</h1>
          <p className="text-xs text-slate-400 mt-1">
            Authoritative revenue, Mobile Money fee absorption, and gate attendance metrics.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <select
            value={selectedEventId}
            onChange={(e) => handleEventFilterChange(e.target.value)}
            className="bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-lg px-3 py-2 focus:ring-1 focus:ring-emerald-500 outline-none"
          >
            <option value="">All Events (Aggregate)</option>
            {events.map((ev) => (
              <option key={ev.id} value={ev.id}>
                {ev.name}
              </option>
            ))}
          </select>

          <button
            onClick={() => downloadCsv("orders")}
            className="px-3.5 py-2 rounded-lg text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition-colors flex items-center gap-2"
          >
            📥 Export Orders CSV
          </button>

          <button
            onClick={() => downloadCsv("attendees")}
            className="px-3.5 py-2 rounded-lg text-xs font-semibold bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 transition-colors flex items-center gap-2"
          >
            📥 Export Attendees CSV
          </button>
        </div>
      </div>

      {error && (
        <div className="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs">
          {error}
        </div>
      )}

      {loading ? (
        <div className="p-12 text-center text-slate-400 text-sm">
          Loading operational financial metrics...
        </div>
      ) : overview ? (
        <>
          {/* High-level Revenue Cards */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div className="p-5 rounded-xl bg-slate-900 border border-slate-800">
              <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                Gross Paid Revenue
              </span>
              <div className="text-2xl font-bold text-white mt-2">
                {formatMoney(overview.revenue.gross_pesewas)}
              </div>
              <div className="text-[11px] text-slate-400 mt-1">
                From {overview.orders.total_paid_orders} paid orders
              </div>
            </div>

            <div className="p-5 rounded-xl bg-slate-900 border border-slate-800">
              <span className="text-xs font-semibold uppercase tracking-wider text-amber-400">
                Paystack Absorbed Fees
              </span>
              <div className="text-2xl font-bold text-amber-300 mt-2">
                {formatMoney(overview.revenue.absorbed_fees_pesewas)}
              </div>
              <div className="text-[11px] text-slate-400 mt-1">
                Absorbed (1.95% rate); customer charged GHS 0 fee
              </div>
            </div>

            <div className="p-5 rounded-xl bg-slate-900 border border-emerald-500/30 bg-gradient-to-b from-emerald-500/5 to-transparent">
              <span className="text-xs font-semibold uppercase tracking-wider text-emerald-400">
                Net Organizer Revenue
              </span>
              <div className="text-2xl font-bold text-emerald-300 mt-2">
                {formatMoney(overview.revenue.net_pesewas)}
              </div>
              <div className="text-[11px] text-emerald-400/80 mt-1">
                Gross revenue minus transaction fees
              </div>
            </div>

            <div className="p-5 rounded-xl bg-slate-900 border border-slate-800">
              <span className="text-xs font-semibold uppercase tracking-wider text-indigo-400">
                Gate Attendance Rate
              </span>
              <div className="text-2xl font-bold text-indigo-300 mt-2">
                {overview.attendance.attendance_rate}%
              </div>
              <div className="text-[11px] text-slate-400 mt-1">
                {overview.attendance.checked_in} checked in / {overview.tickets.total_sold} sold
              </div>
            </div>
          </div>

          {/* Operational Performance & Channel Breakdown */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Payment Provider Breakdown */}
            <div className="p-6 rounded-xl bg-slate-900 border border-slate-800">
              <h2 className="text-sm font-bold text-white mb-4">Mobile Money Channel Distribution</h2>
              <div className="space-y-4">
                {overview.providers.length === 0 ? (
                  <p className="text-xs text-slate-500">No payment transaction records yet.</p>
                ) : (
                  overview.providers.map((p) => {
                    const providerName =
                      p.provider === "mtn"
                        ? "MTN Mobile Money"
                        : p.provider === "vod"
                        ? "Telecel (Vodafone) Cash"
                        : p.provider === "atl"
                        ? "AT Money (AirtelTigo)"
                        : p.provider.toUpperCase();

                    return (
                      <div
                        key={p.provider}
                        className="flex items-center justify-between p-3 rounded-lg bg-slate-950 border border-slate-800"
                      >
                        <div className="flex items-center gap-3">
                          <span className="text-xs font-bold text-emerald-400 uppercase">
                            {p.provider}
                          </span>
                          <div>
                            <p className="text-xs font-semibold text-white">{providerName}</p>
                            <p className="text-[11px] text-slate-400">{p.count} successful transactions</p>
                          </div>
                        </div>
                        <div className="text-right">
                          <p className="text-xs font-bold text-white">{formatMoney(p.total_pesewas)}</p>
                        </div>
                      </div>
                    );
                  })
                )}
              </div>
            </div>

            {/* Order Funnel & Conversion */}
            <div className="p-6 rounded-xl bg-slate-900 border border-slate-800">
              <h2 className="text-sm font-bold text-white mb-4">Checkout & Conversion Health</h2>
              <div className="space-y-4">
                <div className="flex justify-between items-center text-xs text-slate-300 pb-2 border-b border-slate-800">
                  <span>Total Initiated Orders</span>
                  <span className="font-semibold text-white">{overview.orders.total_orders}</span>
                </div>
                <div className="flex justify-between items-center text-xs text-slate-300 pb-2 border-b border-slate-800">
                  <span>Successful Paid Orders</span>
                  <span className="font-semibold text-emerald-400">{overview.orders.total_paid_orders}</span>
                </div>
                <div className="flex justify-between items-center text-xs text-slate-300 pb-2 border-b border-slate-800">
                  <span>Pending / Expired Orders</span>
                  <span className="font-semibold text-amber-400">{overview.orders.pending_orders}</span>
                </div>
                <div className="flex justify-between items-center text-xs text-slate-300">
                  <span>Conversion Rate</span>
                  <span className="font-bold text-white">{overview.orders.conversion_rate}%</span>
                </div>
              </div>
            </div>
          </div>

          {/* Ticket Tier Breakdown Table */}
          <div className="p-6 rounded-xl bg-slate-900 border border-slate-800">
            <h2 className="text-sm font-bold text-white mb-4">Ticket Tier Inventory & Revenue</h2>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs text-slate-300">
                <thead className="text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-800 bg-slate-950/50">
                  <tr>
                    <th className="py-3 px-4">Tier Name</th>
                    <th className="py-3 px-4">Price</th>
                    <th className="py-3 px-4">Sold</th>
                    <th className="py-3 px-4">Reserved</th>
                    <th className="py-3 px-4">Capacity</th>
                    <th className="py-3 px-4 text-right">Gross Total</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800">
                  {overview.tiers.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="py-6 text-center text-slate-500">
                        No ticket tiers found.
                      </td>
                    </tr>
                  ) : (
                    overview.tiers.map((tier) => (
                      <tr key={tier.id} className="hover:bg-slate-800/40">
                        <td className="py-3 px-4 font-semibold text-white">{tier.name}</td>
                        <td className="py-3 px-4">{formatMoney(tier.price_pesewas)}</td>
                        <td className="py-3 px-4 text-emerald-400 font-semibold">{tier.sold}</td>
                        <td className="py-3 px-4 text-amber-400">{tier.reserved}</td>
                        <td className="py-3 px-4">{tier.total}</td>
                        <td className="py-3 px-4 text-right font-bold text-white">
                          {formatMoney(tier.revenue_pesewas)}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </>
      ) : null}
    </div>
  );
}

