"use client";

import { useEffect, useState, useCallback, use } from "react";
import Link from "next/link";
import { apiClient, ApiError } from "@/lib/api-client";
import { EventItem, TicketType } from "@/types/event";
import { formatEventDate, formatMoney } from "@/lib/utils";

interface PageProps {
  params: Promise<{ id: string }>;
}

export default function AdminEventDetailPage({ params }: PageProps) {
  const { id } = use(params);

  const [event, setEvent] = useState<EventItem | null>(null);
  const [ticketTypes, setTicketTypes] = useState<TicketType[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [message, setMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);

  // Ticket Type Modal / Form State
  const [showAddTicketModal, setShowAddTicketModal] = useState(false);
  const [ticketName, setTicketName] = useState("");
  const [ticketDesc, setTicketDesc] = useState("");
  const [ticketPriceGhs, setTicketPriceGhs] = useState<number>(0);
  const [ticketQuantity, setTicketQuantity] = useState<number>(100);
  const [ticketMinOrder, setTicketMinOrder] = useState<number>(1);
  const [ticketMaxOrder, setTicketMaxOrder] = useState<number>(10);
  const [ticketSalesStart, setTicketSalesStart] = useState("");
  const [ticketSalesEnd, setTicketSalesEnd] = useState("");
  const [ticketError, setTicketError] = useState<string | null>(null);

  // Banner Upload State
  const [bannerFile, setBannerFile] = useState<File | null>(null);
  const [uploadingBanner, setUploadingBanner] = useState(false);

  const loadEventData = useCallback(async () => {
    try {
      setLoading(true);
      const [eventRes, ticketsRes] = await Promise.all([
        apiClient<{ data: EventItem }>(`/api/v1/admin/events/${id}`),
        apiClient<{ data: TicketType[] }>(`/api/v1/admin/events/${id}/ticket-types`),
      ]);
      setEvent(eventRes.data);
      setTicketTypes(ticketsRes.data);
    } catch (err) {
      if (err instanceof ApiError) {
        setMessage({ type: "error", text: err.message });
      } else {
        setMessage({ type: "error", text: "Failed to load event details." });
      }
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadEventData();
  }, [loadEventData]);

  const handleStatusTransition = async (action: "publish" | "unpublish" | "cancel" | "archive") => {
    if (!event) return;

    if (action === "cancel") {
      const confirmed = window.confirm(
        "Are you sure you want to cancel this event? Customers will no longer be able to purchase tickets."
      );
      if (!confirmed) return;
    }

    try {
      setActionLoading(true);
      setMessage(null);
      const res = await apiClient<{ data: EventItem; message: string }>(
        `/api/v1/admin/events/${id}/${action}`,
        { method: "POST" }
      );
      setEvent(res.data);
      setMessage({ type: "success", text: res.message || `Event status changed to ${action}.` });
    } catch (err) {
      if (err instanceof ApiError) {
        setMessage({ type: "error", text: err.message });
      } else {
        setMessage({ type: "error", text: `Failed to ${action} event.` });
      }
    } finally {
      setActionLoading(false);
    }
  };

  const handleBannerUpload = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!bannerFile || !event) return;

    try {
      setUploadingBanner(true);
      setMessage(null);
      const formData = new FormData();
      formData.append("banner", bannerFile);

      const res = await apiClient<{ data: EventItem; message: string }>(
        `/api/v1/admin/events/${id}/banner`,
        {
          method: "POST",
          body: formData,
        }
      );
      setEvent(res.data);
      setBannerFile(null);
      setMessage({ type: "success", text: "Banner image updated successfully." });
    } catch (err) {
      if (err instanceof ApiError) {
        setMessage({ type: "error", text: err.message });
      } else {
        setMessage({ type: "error", text: "Failed to upload banner." });
      }
    } finally {
      setUploadingBanner(false);
    }
  };

  const handleCreateTicketType = async (e: React.FormEvent) => {
    e.preventDefault();
    setTicketError(null);

    try {
      const priceMinor = Math.round(ticketPriceGhs * 100);
      const payload: Record<string, unknown> = {
        name: ticketName,
        description: ticketDesc.trim() || undefined,
        price_minor: priceMinor,
        quantity_total: ticketQuantity,
        min_per_order: ticketMinOrder,
        max_per_order: ticketMaxOrder,
        sales_starts_at: ticketSalesStart ? new Date(ticketSalesStart).toISOString() : undefined,
        sales_ends_at: ticketSalesEnd ? new Date(ticketSalesEnd).toISOString() : undefined,
      };

      const res = await apiClient<{ data: TicketType; message: string }>(
        `/api/v1/admin/events/${id}/ticket-types`,
        {
          method: "POST",
          body: JSON.stringify(payload),
        }
      );

      setTicketTypes((prev) => [...prev, res.data]);
      setShowAddTicketModal(false);
      // Reset form
      setTicketName("");
      setTicketDesc("");
      setTicketPriceGhs(0);
      setTicketQuantity(100);
      setMessage({ type: "success", text: "Ticket category added successfully." });
    } catch (err) {
      if (err instanceof ApiError) {
        setTicketError(err.message);
      } else {
        setTicketError("Failed to add ticket type.");
      }
    }
  };

  const handleArchiveTicket = async (ticketTypeId: string) => {
    const confirmed = window.confirm("Are you sure you want to archive this ticket category?");
    if (!confirmed) return;

    try {
      await apiClient(`/api/v1/admin/events/${id}/ticket-types/${ticketTypeId}/archive`, {
        method: "POST",
      });
      setTicketTypes((prev) =>
        prev.map((t) => (t.id === ticketTypeId ? { ...t, status: "archived" } : t))
      );
      setMessage({ type: "success", text: "Ticket category archived." });
    } catch (err) {
      if (err instanceof ApiError) {
        setMessage({ type: "error", text: err.message });
      } else {
        setMessage({ type: "error", text: "Failed to archive ticket." });
      }
    }
  };

  if (loading) {
    return (
      <div className="p-12 text-center text-slate-400 text-xs">
        <div className="inline-block animate-spin text-xl mb-2">⏳</div>
        <p>Loading event details...</p>
      </div>
    );
  }

  if (!event) {
    return (
      <div className="p-8 text-center space-y-4">
        <h2 className="text-xl font-bold text-white">Event Not Found</h2>
        <Link
          href="/admin/events"
          className="px-4 py-2 rounded-lg text-xs font-semibold bg-emerald-500 text-slate-950 inline-block"
        >
          ← Back to Events
        </Link>
      </div>
    );
  }

  const isPublished = event.status === "published";

  return (
    <div className="space-y-8 max-w-5xl">
      {/* Breadcrumb & Header */}
      <div>
        <div className="flex items-center gap-2 text-xs text-slate-400 mb-1">
          <Link href="/admin/events" className="hover:text-white transition-colors">
            ← Events
          </Link>
          <span>/</span>
          <span className="font-mono text-slate-400">{event.slug}</span>
        </div>

        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-2xl font-bold text-white tracking-tight">{event.name}</h1>
              <span
                className={`px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize ${
                  isPublished
                    ? "bg-emerald-950/80 text-emerald-400 border border-emerald-800"
                    : event.status === "cancelled"
                    ? "bg-red-950/80 text-red-400 border border-red-800"
                    : "bg-amber-950/80 text-amber-400 border border-amber-800"
                }`}
              >
                {event.status}
              </span>
            </div>
            <p className="text-xs text-slate-400 mt-1">
              📍 {event.venue_name || "TBA"} • Starts: {formatEventDate(event.starts_at, event.timezone, false)}
            </p>
          </div>

          {/* Lifecycle Action Buttons */}
          <div className="flex flex-wrap gap-2 items-center">
            {isPublished && (
              <Link
                href={`/events/${event.slug}`}
                target="_blank"
                className="px-3.5 py-2 rounded-lg text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 transition-colors"
              >
                Public Page ↗
              </Link>
            )}

            {event.status === "draft" || event.status === "unpublished" ? (
              <button
                type="button"
                onClick={() => handleStatusTransition("publish")}
                disabled={actionLoading}
                className="px-4 py-2 rounded-lg text-xs font-semibold bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 text-slate-950 transition-colors shadow-lg shadow-emerald-500/20"
              >
                Publish Event
              </button>
            ) : isPublished ? (
              <button
                type="button"
                onClick={() => handleStatusTransition("unpublish")}
                disabled={actionLoading}
                className="px-3.5 py-2 rounded-lg text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors"
              >
                Unpublish
              </button>
            ) : null}

            {event.status !== "cancelled" && event.status !== "archived" && (
              <button
                type="button"
                onClick={() => handleStatusTransition("cancel")}
                disabled={actionLoading}
                className="px-3 py-2 rounded-lg text-xs font-semibold bg-red-950/60 hover:bg-red-900/60 text-red-400 border border-red-800/80 transition-colors"
              >
                Cancel Event
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Notice Message */}
      {message && (
        <div
          className={`p-4 rounded-xl text-xs flex justify-between items-center ${
            message.type === "success"
              ? "bg-emerald-950/60 border border-emerald-800 text-emerald-300"
              : "bg-red-950/60 border border-red-800 text-red-300"
          }`}
        >
          <span>{message.text}</span>
          <button
            type="button"
            onClick={() => setMessage(null)}
            className="text-slate-400 hover:text-white"
          >
            ✕
          </button>
        </div>
      )}

      {/* Grid: Details & Banner Upload */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {/* Banner Section */}
        <div className="md:col-span-1 bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-4">
          <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">
            Event Banner
          </h3>
          <div className="aspect-[16/9] rounded-xl bg-slate-950 border border-slate-800 overflow-hidden flex items-center justify-center">
            {event.banner_url ? (
              /* eslint-disable-next-line @next/next/no-img-element */
              <img
                src={event.banner_url}
                alt={event.name}
                className="w-full h-full object-cover"
              />
            ) : (
              <div className="text-4xl text-slate-400">🎟️</div>
            )}
          </div>

          <form onSubmit={handleBannerUpload} className="space-y-3">
            <input
              type="file"
              accept="image/png,image/jpeg,image/webp"
              onChange={(e) => setBannerFile(e.target.files?.[0] || null)}
              className="text-xs text-slate-400 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-slate-300 hover:file:bg-slate-700 w-full"
            />
            {bannerFile && (
              <button
                type="submit"
                disabled={uploadingBanner}
                className="w-full py-2 rounded-lg text-xs font-semibold bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 text-slate-950 transition-colors"
              >
                {uploadingBanner ? "Uploading..." : "Save Banner"}
              </button>
            )}
          </form>
        </div>

        {/* Schedule & Overview */}
        <div className="md:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
          <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">
            Event Overview
          </h3>

          <div className="grid grid-cols-2 gap-4 text-xs">
            <div>
              <span className="text-slate-400">Starts At:</span>
              <p className="text-white font-medium mt-0.5">
                {formatEventDate(event.starts_at, event.timezone, true)}
              </p>
            </div>
            <div>
              <span className="text-slate-400">Ends At:</span>
              <p className="text-white font-medium mt-0.5">
                {formatEventDate(event.ends_at, event.timezone, true)}
              </p>
            </div>
            <div>
              <span className="text-slate-400">Venue:</span>
              <p className="text-white font-medium mt-0.5">{event.venue_name || "TBA"}</p>
              {event.venue_address && (
                <p className="text-slate-400 text-[11px]">{event.venue_address}</p>
              )}
            </div>
            <div>
              <span className="text-slate-400">Timezone:</span>
              <p className="text-white font-medium mt-0.5">{event.timezone}</p>
            </div>
          </div>

          {event.description && (
            <div className="pt-2 border-t border-slate-800/80">
              <span className="text-xs text-slate-400">Description:</span>
              <p className="text-xs text-slate-300 mt-1 whitespace-pre-line line-clamp-3">
                {event.description}
              </p>
            </div>
          )}
        </div>
      </div>

      {/* Ticket Categories Management Section */}
      <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-6">
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-800 pb-4">
          <div>
            <h2 className="text-lg font-bold text-white">Ticket Categories</h2>
            <p className="text-xs text-slate-400 mt-0.5">
              Define pricing tiers, quantities, purchase limits, and sales windows.
            </p>
          </div>

          <button
            type="button"
            onClick={() => setShowAddTicketModal(true)}
            className="px-3.5 py-2 rounded-lg text-xs font-semibold bg-emerald-500 hover:bg-emerald-600 text-slate-950 transition-colors flex items-center gap-1.5 shadow-md shadow-emerald-500/20"
          >
            <span>+</span> Add Ticket Category
          </button>
        </div>

        {/* Ticket Categories Table */}
        {ticketTypes.length === 0 ? (
          <div className="p-8 text-center space-y-2">
            <p className="text-xs text-slate-400">
              No ticket categories configured. Add at least one ticket category before publishing.
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-950/60 border-b border-slate-800 text-slate-400 uppercase font-semibold">
                <tr>
                  <th className="px-4 py-3">Category</th>
                  <th className="px-4 py-3">Price</th>
                  <th className="px-4 py-3">Total</th>
                  <th className="px-4 py-3">Sold</th>
                  <th className="px-4 py-3">Reserved</th>
                  <th className="px-4 py-3">Available</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/80">
                {ticketTypes.map((tt) => (
                  <tr key={tt.id} className="hover:bg-slate-800/30 transition-colors">
                    <td className="px-4 py-3.5">
                      <div className="font-bold text-white">{tt.name}</div>
                      {tt.description && (
                        <div className="text-[11px] text-slate-400 mt-0.5 line-clamp-1">
                          {tt.description}
                        </div>
                      )}
                    </td>
                    <td className="px-4 py-3.5 font-semibold text-white">
                      {formatMoney(tt.price_minor)}
                    </td>
                    <td className="px-4 py-3.5 text-slate-300">{tt.quantity_total}</td>
                    <td className="px-4 py-3.5 text-emerald-400 font-medium">{tt.quantity_sold}</td>
                    <td className="px-4 py-3.5 text-amber-400 font-medium">{tt.quantity_reserved}</td>
                    <td className="px-4 py-3.5 text-slate-300 font-bold">{tt.available_quantity}</td>
                    <td className="px-4 py-3.5">
                      <span
                        className={`px-2 py-0.5 rounded text-[10px] font-semibold uppercase ${
                          tt.status === "active"
                            ? "bg-emerald-950/80 text-emerald-400 border border-emerald-800"
                            : "bg-slate-800 text-slate-400"
                        }`}
                      >
                        {tt.status}
                      </span>
                    </td>
                    <td className="px-4 py-3.5 text-right">
                      {tt.status === "active" && (
                        <button
                          type="button"
                          onClick={() => handleArchiveTicket(tt.id)}
                          className="px-2.5 py-1 rounded bg-slate-800 hover:bg-red-950/60 hover:text-red-400 text-slate-400 text-[11px] transition-colors"
                        >
                          Archive
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Add Ticket Modal */}
      {showAddTicketModal && (
        <div className="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 space-y-5 shadow-2xl">
            <div className="flex justify-between items-center border-b border-slate-800 pb-3">
              <h3 className="text-base font-bold text-white">Add Ticket Category</h3>
              <button
                type="button"
                onClick={() => setShowAddTicketModal(false)}
                className="text-slate-400 hover:text-white"
              >
                ✕
              </button>
            </div>

            {ticketError && (
              <div className="p-3 rounded-lg bg-red-950/60 border border-red-800 text-xs text-red-300">
                {ticketError}
              </div>
            )}

            <form onSubmit={handleCreateTicketType} className="space-y-4 text-xs">
              <div>
                <label className="block font-semibold text-slate-300 mb-1">
                  Category Name <span className="text-emerald-400">*</span>
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. VIP Pass, Early Bird, General Admission"
                  value={ticketName}
                  onChange={(e) => setTicketName(e.target.value)}
                  className="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-white focus:outline-none focus:border-emerald-500"
                />
              </div>

              <div>
                <label className="block font-semibold text-slate-300 mb-1">Description</label>
                <textarea
                  rows={2}
                  placeholder="e.g. Includes backstage access and complimentary drink"
                  value={ticketDesc}
                  onChange={(e) => setTicketDesc(e.target.value)}
                  className="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-white focus:outline-none focus:border-emerald-500"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-semibold text-slate-300 mb-1">
                    Price (GH₵) <span className="text-emerald-400">*</span>
                  </label>
                  <input
                    type="number"
                    min="0"
                    step="0.50"
                    required
                    value={ticketPriceGhs}
                    onChange={(e) => setTicketPriceGhs(parseFloat(e.target.value) || 0)}
                    className="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-white focus:outline-none focus:border-emerald-500"
                  />
                  <p className="text-[10px] text-slate-400 mt-0.5">
                    Minor units: {Math.round(ticketPriceGhs * 100)} pesewas
                  </p>
                </div>

                <div>
                  <label className="block font-semibold text-slate-300 mb-1">
                    Total Quantity <span className="text-emerald-400">*</span>
                  </label>
                  <input
                    type="number"
                    min="1"
                    required
                    value={ticketQuantity}
                    onChange={(e) => setTicketQuantity(parseInt(e.target.value) || 1)}
                    className="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-white focus:outline-none focus:border-emerald-500"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-semibold text-slate-300 mb-1">Min Per Order</label>
                  <input
                    type="number"
                    min="1"
                    value={ticketMinOrder}
                    onChange={(e) => setTicketMinOrder(parseInt(e.target.value) || 1)}
                    className="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-white focus:outline-none focus:border-emerald-500"
                  />
                </div>

                <div>
                  <label className="block font-semibold text-slate-300 mb-1">Max Per Order</label>
                  <input
                    type="number"
                    min="1"
                    value={ticketMaxOrder}
                    onChange={(e) => setTicketMaxOrder(parseInt(e.target.value) || 1)}
                    className="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-white focus:outline-none focus:border-emerald-500"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-semibold text-slate-300 mb-1">
                    Sales Start (Optional)
                  </label>
                  <input
                    type="datetime-local"
                    value={ticketSalesStart}
                    onChange={(e) => setTicketSalesStart(e.target.value)}
                    className="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-white focus:outline-none focus:border-emerald-500 [color-scheme:dark]"
                  />
                </div>

                <div>
                  <label className="block font-semibold text-slate-300 mb-1">
                    Sales End (Optional)
                  </label>
                  <input
                    type="datetime-local"
                    value={ticketSalesEnd}
                    onChange={(e) => setTicketSalesEnd(e.target.value)}
                    className="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-white focus:outline-none focus:border-emerald-500 [color-scheme:dark]"
                  />
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowAddTicketModal(false)}
                  className="px-4 py-2 rounded-lg text-slate-400 hover:text-white"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-lg font-semibold bg-emerald-500 hover:bg-emerald-600 text-slate-950 transition-colors shadow-md shadow-emerald-500/20"
                >
                  Create Category
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
