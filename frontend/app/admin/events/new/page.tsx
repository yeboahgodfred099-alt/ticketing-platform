"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { apiClient, ApiError } from "@/lib/api-client";
import { EventItem } from "@/types/event";

export default function NewEventPage() {
  const router = useRouter();

  const [name, setName] = useState("");
  const [slug, setSlug] = useState("");
  const [description, setDescription] = useState("");
  const [venueName, setVenueName] = useState("");
  const [venueAddress, setVenueAddress] = useState("");
  const [startsAt, setStartsAt] = useState("");
  const [endsAt, setEndsAt] = useState("");
  const [salesStartsAt, setSalesStartsAt] = useState("");
  const [salesEndsAt, setSalesEndsAt] = useState("");
  const [timezone, setTimezone] = useState("Africa/Accra");

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  const handleNameChange = (val: string) => {
    setName(val);
    // Auto-generate slug if slug hasn't been manually set or edited
    const generated = val
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "");
    setSlug(generated);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    setFieldErrors({});

    try {
      const payload: Record<string, unknown> = {
        name,
        slug: slug.trim() || undefined,
        description: description.trim() || undefined,
        venue_name: venueName.trim() || undefined,
        venue_address: venueAddress.trim() || undefined,
        starts_at: startsAt ? new Date(startsAt).toISOString() : undefined,
        ends_at: endsAt ? new Date(endsAt).toISOString() : undefined,
        sales_starts_at: salesStartsAt ? new Date(salesStartsAt).toISOString() : undefined,
        sales_ends_at: salesEndsAt ? new Date(salesEndsAt).toISOString() : undefined,
        timezone,
      };

      const res = await apiClient<{ data: EventItem }>("/api/v1/admin/events", {
        method: "POST",
        body: JSON.stringify(payload),
      });

      router.push(`/admin/events/${res.data.id}`);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
        if (err.errors) {
          setFieldErrors(err.errors);
        }
      } else {
        setError("An unexpected error occurred while creating the event.");
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-3xl space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <div className="flex items-center gap-2 text-xs text-slate-400 mb-1">
            <Link href="/admin/events" className="hover:text-white transition-colors">
              ← Events
            </Link>
            <span>/</span>
            <span>New Event</span>
          </div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Create New Event</h1>
          <p className="text-xs text-slate-400 mt-0.5">
            Set up event details. You can configure ticket tiers and upload banners on the next screen.
          </p>
        </div>
      </div>

      {error && (
        <div className="p-4 rounded-xl bg-red-950/60 border border-red-800 text-xs text-red-300">
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 space-y-6">
        {/* Basic Info */}
        <div className="space-y-4">
          <h3 className="text-sm font-bold text-white uppercase tracking-wider border-b border-slate-800 pb-2">
            1. Event Information
          </h3>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1.5">
              Event Name <span className="text-emerald-400">*</span>
            </label>
            <input
              type="text"
              required
              value={name}
              onChange={(e) => handleNameChange(e.target.value)}
              placeholder="e.g. Accra Tech Summit 2026"
              className="w-full px-4 py-2.5 rounded-lg bg-slate-950 border border-slate-700 text-sm text-white focus:outline-none focus:border-emerald-500"
            />
            {fieldErrors.name && (
              <p className="text-xs text-red-400 mt-1">{fieldErrors.name[0]}</p>
            )}
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1.5">
              URL Slug <span className="text-slate-400">(Auto-generated or custom)</span>
            </label>
            <div className="flex items-center">
              <span className="px-3 py-2.5 rounded-l-lg bg-slate-800 border border-r-0 border-slate-700 text-xs text-slate-400 font-mono">
                /events/
              </span>
              <input
                type="text"
                value={slug}
                onChange={(e) => setSlug(e.target.value)}
                placeholder="accra-tech-summit-2026"
                className="w-full px-4 py-2.5 rounded-r-lg bg-slate-950 border border-slate-700 text-sm text-white font-mono focus:outline-none focus:border-emerald-500"
              />
            </div>
            {fieldErrors.slug && (
              <p className="text-xs text-red-400 mt-1">{fieldErrors.slug[0]}</p>
            )}
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1.5">
              Description
            </label>
            <textarea
              rows={4}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Describe the event, lineup, special instructions, and what attendees should expect..."
              className="w-full px-4 py-2.5 rounded-lg bg-slate-950 border border-slate-700 text-sm text-white focus:outline-none focus:border-emerald-500 resize-y"
            />
            {fieldErrors.description && (
              <p className="text-xs text-red-400 mt-1">{fieldErrors.description[0]}</p>
            )}
          </div>
        </div>

        {/* Schedule */}
        <div className="space-y-4 pt-4">
          <h3 className="text-sm font-bold text-white uppercase tracking-wider border-b border-slate-800 pb-2">
            2. Schedule & Timezone
          </h3>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1.5">
                Event Starts At <span className="text-emerald-400">*</span>
              </label>
              <input
                type="datetime-local"
                required
                value={startsAt}
                onChange={(e) => setStartsAt(e.target.value)}
                className="w-full px-4 py-2.5 rounded-lg bg-slate-950 border border-slate-700 text-sm text-white focus:outline-none focus:border-emerald-500 [color-scheme:dark]"
              />
              {fieldErrors.starts_at && (
                <p className="text-xs text-red-400 mt-1">{fieldErrors.starts_at[0]}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1.5">
                Event Ends At <span className="text-emerald-400">*</span>
              </label>
              <input
                type="datetime-local"
                required
                value={endsAt}
                onChange={(e) => setEndsAt(e.target.value)}
                className="w-full px-4 py-2.5 rounded-lg bg-slate-950 border border-slate-700 text-sm text-white focus:outline-none focus:border-emerald-500 [color-scheme:dark]"
              />
              {fieldErrors.ends_at && (
                <p className="text-xs text-red-400 mt-1">{fieldErrors.ends_at[0]}</p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1.5">
                Ticket Sales Open <span className="text-slate-400">(Optional)</span>
              </label>
              <input
                type="datetime-local"
                value={salesStartsAt}
                onChange={(e) => setSalesStartsAt(e.target.value)}
                className="w-full px-4 py-2.5 rounded-lg bg-slate-950 border border-slate-700 text-sm text-white focus:outline-none focus:border-emerald-500 [color-scheme:dark]"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1.5">
                Ticket Sales Close <span className="text-slate-400">(Optional)</span>
              </label>
              <input
                type="datetime-local"
                value={salesEndsAt}
                onChange={(e) => setSalesEndsAt(e.target.value)}
                className="w-full px-4 py-2.5 rounded-lg bg-slate-950 border border-slate-700 text-sm text-white focus:outline-none focus:border-emerald-500 [color-scheme:dark]"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1.5">
              Timezone
            </label>
            <input
              type="text"
              value={timezone}
              onChange={(e) => setTimezone(e.target.value)}
              className="w-full px-4 py-2.5 rounded-lg bg-slate-950 border border-slate-700 text-sm text-white focus:outline-none focus:border-emerald-500"
            />
            <p className="text-[11px] text-slate-400 mt-1">Default is Africa/Accra (GMT).</p>
          </div>
        </div>

        {/* Location */}
        <div className="space-y-4 pt-4">
          <h3 className="text-sm font-bold text-white uppercase tracking-wider border-b border-slate-800 pb-2">
            3. Venue & Location
          </h3>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1.5">
                Venue Name
              </label>
              <input
                type="text"
                value={venueName}
                onChange={(e) => setVenueName(e.target.value)}
                placeholder="e.g. Grand Arena, AICC"
                className="w-full px-4 py-2.5 rounded-lg bg-slate-950 border border-slate-700 text-sm text-white focus:outline-none focus:border-emerald-500"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1.5">
                Venue Address
              </label>
              <input
                type="text"
                value={venueAddress}
                onChange={(e) => setVenueAddress(e.target.value)}
                placeholder="e.g. Castle Road, Osu, Accra"
                className="w-full px-4 py-2.5 rounded-lg bg-slate-950 border border-slate-700 text-sm text-white focus:outline-none focus:border-emerald-500"
              />
            </div>
          </div>
        </div>

        {/* Form Actions */}
        <div className="pt-6 border-t border-slate-800 flex items-center justify-between">
          <Link
            href="/admin/events"
            className="px-4 py-2 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition-colors"
          >
            Cancel
          </Link>

          <button
            type="submit"
            disabled={loading}
            className="px-6 py-2.5 rounded-lg text-xs font-semibold bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 text-slate-950 transition-colors shadow-lg shadow-emerald-500/20"
          >
            {loading ? "Creating Event..." : "Create Event & Configure Tickets →"}
          </button>
        </div>
      </form>
    </div>
  );
}
