"use client";

import { useEffect, useState, useRef, useCallback } from "react";
import Link from "next/link";
import { useAuth } from "@/hooks/useAuth";
import { apiClient, ApiError } from "@/lib/api-client";
import { EventItem } from "@/types/event";

interface CheckInOutcome {
  outcome: "valid" | "already_used" | "wrong_event" | "invalid" | "cancelled";
  message: string;
  data: {
    id?: string;
    ticket_number?: string;
    holder_name?: string;
    category?: string;
    status?: string;
    checked_in_at?: string;
    prior_check_in?: {
      checked_in_at: string;
      gate?: string;
      staff_name?: string;
    } | null;
  } | null;
}

interface GateStats {
  total_sold: number;
  total_checked_in: number;
  remaining_to_arrive: number;
  check_in_percentage: number;
  recent_check_ins: Array<{
    ticket_number: string;
    holder_name: string;
    category: string;
    gate: string | null;
    checked_in_at: string;
    staff_name: string | null;
  }>;
}

export default function GateScanPage() {
  const { admin, loading: authLoading } = useAuth(true);

  const [events, setEvents] = useState<EventItem[]>([]);
  const [selectedEventId, setSelectedEventId] = useState<string>("");
  const [gateIdentifier, setGateIdentifier] = useState<string>("Gate 1");

  // Manual input state
  const [manualCode, setManualCode] = useState<string>("");
  const [scanning, setScanning] = useState<boolean>(false);
  const [lastResult, setLastResult] = useState<CheckInOutcome | null>(null);
  const [stats, setStats] = useState<GateStats | null>(null);

  const inputRef = useRef<HTMLInputElement>(null);

  // Play synthesized audio tone using Web Audio API
  const playTone = (type: "success" | "error" | "warning") => {
    try {
      const AudioCtx = window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);

      if (type === "success") {
        osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
        osc.frequency.setValueAtTime(880, ctx.currentTime + 0.1); // A5
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.3);
      } else if (type === "error") {
        osc.type = "sawtooth";
        osc.frequency.setValueAtTime(150, ctx.currentTime);
        osc.frequency.setValueAtTime(110, ctx.currentTime + 0.15);
        gain.gain.setValueAtTime(0.4, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.4);
      } else {
        osc.frequency.setValueAtTime(440, ctx.currentTime);
        gain.gain.setValueAtTime(0.2, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.2);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.2);
      }
    } catch {
      // Audio autoplay disabled or unsupported
    }
  };

  // Fetch available events for staffing
  useEffect(() => {
    apiClient<{ data: EventItem[] }>("/api/v1/public/events")
      .then((res) => {
        setEvents(res.data);
        if (res.data.length > 0 && !selectedEventId) {
          setSelectedEventId(res.data[0].id);
        }
      })
      .catch(() => setEvents([]));
  }, [selectedEventId]);

  // Fetch gate stats for selected event
  const fetchStats = useCallback(async () => {
    if (!selectedEventId) return;
    try {
      const res = await apiClient<{ data: { stats: GateStats } }>(
        `/api/v1/staff/events/${selectedEventId}/stats`
      );
      setStats(res.data.stats);
    } catch {
      // stats unavailable
    }
  }, [selectedEventId]);

  useEffect(() => {
    fetchStats();
    const interval = setInterval(fetchStats, 10000);
    return () => clearInterval(interval);
  }, [fetchStats]);

  const handleProcessScan = async (codeToVerify: string) => {
    const code = codeToVerify.trim();
    if (!code || !selectedEventId) return;

    setScanning(true);

    try {
      const res = await apiClient<CheckInOutcome>("/api/v1/staff/check-in", {
        method: "POST",
        body: JSON.stringify({
          event_id: selectedEventId,
          token: code,
          gate_identifier: gateIdentifier || undefined,
        }),
      });

      setLastResult(res);

      if (res.outcome === "valid") {
        playTone("success");
      } else if (res.outcome === "already_used") {
        playTone("error");
      } else {
        playTone("warning");
      }

      setManualCode("");
      fetchStats();
      inputRef.current?.focus();
    } catch (err) {
      if (err instanceof ApiError) {
        setLastResult({
          outcome: "invalid",
          message: err.message,
          data: null,
        });
        playTone("error");
      }
    } finally {
      setScanning(false);
    }
  };

  const handleManualSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    handleProcessScan(manualCode);
  };

  if (authLoading) {
    return (
      <div className="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-8">
        <div className="text-center space-y-3">
          <div className="text-3xl animate-spin">⏳</div>
          <p className="text-xs text-slate-400">Authenticating gate personnel...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans">
      {/* Top Navbar */}
      <header className="border-b border-slate-800 bg-slate-900/90 backdrop-blur sticky top-0 z-50">
        <div className="max-w-4xl mx-auto px-4 h-16 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <span className="text-lg font-black tracking-tight text-white">
              Gate <span className="text-emerald-400">Scanner</span>
            </span>
            <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-950 text-emerald-400 border border-emerald-800">
              Staff Portal
            </span>
          </div>

          <div className="flex items-center gap-4 text-xs">
            <span className="text-slate-400 hidden sm:inline">
              👤 {admin?.name || "Gate Staff"}
            </span>
            <Link
              href="/admin/dashboard"
              className="text-slate-400 hover:text-white transition-colors"
            >
              Dashboard ↗
            </Link>
          </div>
        </div>
      </header>

      {/* Main Staff Dashboard */}
      <main className="flex-1 max-w-4xl mx-auto px-4 py-8 w-full space-y-6">
        {/* Event & Gate Selection */}
        <div className="p-5 rounded-2xl bg-slate-900 border border-slate-800 grid grid-cols-1 sm:grid-cols-2 gap-4 shadow-xl">
          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1.5">
              Assigned Event
            </label>
            <select
              value={selectedEventId}
              onChange={(e) => {
                setSelectedEventId(e.target.value);
                setLastResult(null);
              }}
              className="w-full px-3.5 py-2.5 rounded-lg bg-slate-950 border border-slate-700 text-xs text-white focus:outline-none focus:border-emerald-500"
            >
              {events.map((ev) => (
                <option key={ev.id} value={ev.id}>
                  {ev.name} ({ev.venue_name || "TBA"})
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1.5">
              Gate / Turnstile Name
            </label>
            <input
              type="text"
              value={gateIdentifier}
              onChange={(e) => setGateIdentifier(e.target.value)}
              placeholder="e.g. Gate 1, VIP Entrance, North Gate"
              className="w-full px-3.5 py-2.5 rounded-lg bg-slate-950 border border-slate-700 text-xs text-white focus:outline-none focus:border-emerald-500"
            />
          </div>
        </div>

        {/* Live Gate Counters */}
        {stats && (
          <div className="grid grid-cols-3 gap-3">
            <div className="p-4 rounded-xl bg-slate-900 border border-slate-800 text-center">
              <span className="text-[10px] uppercase font-bold text-slate-400">Total Sold</span>
              <div className="text-xl sm:text-2xl font-black text-white mt-0.5">
                {stats.total_sold}
              </div>
            </div>

            <div className="p-4 rounded-xl bg-slate-900 border border-emerald-500/30 text-center">
              <span className="text-[10px] uppercase font-bold text-emerald-400">Checked In</span>
              <div className="text-xl sm:text-2xl font-black text-emerald-400 mt-0.5">
                {stats.total_checked_in}
              </div>
              <span className="text-[10px] text-slate-400">{stats.check_in_percentage}%</span>
            </div>

            <div className="p-4 rounded-xl bg-slate-900 border border-slate-800 text-center">
              <span className="text-[10px] uppercase font-bold text-slate-400">Remaining</span>
              <div className="text-xl sm:text-2xl font-black text-slate-300 mt-0.5">
                {stats.remaining_to_arrive}
              </div>
            </div>
          </div>
        )}

        {/* Visual Outcome Flash Screen */}
        {lastResult && (
          <div
            className={`p-6 sm:p-8 rounded-2xl border text-center transition-all animate-in fade-in zoom-in-95 duration-200 shadow-2xl ${
              lastResult.outcome === "valid"
                ? "bg-emerald-950/90 border-emerald-500 text-emerald-200 shadow-emerald-950/40"
                : lastResult.outcome === "already_used"
                ? "bg-red-950/90 border-red-500 text-red-200 shadow-red-950/40"
                : "bg-amber-950/90 border-amber-500 text-amber-200 shadow-amber-950/40"
            }`}
          >
            <div className="text-5xl mb-2">
              {lastResult.outcome === "valid" ? "✅" : lastResult.outcome === "already_used" ? "🚫" : "⚠️"}
            </div>

            <h2 className="text-2xl sm:text-3xl font-black tracking-tight uppercase text-white">
              {lastResult.outcome === "valid"
                ? "ADMISSION GRANTED"
                : lastResult.outcome === "already_used"
                ? "ALREADY CHECKED IN"
                : lastResult.outcome === "wrong_event"
                ? "WRONG EVENT"
                : "INVALID TICKET"}
            </h2>

            <p className="text-sm font-medium mt-2 max-w-md mx-auto">
              {lastResult.message}
            </p>

            {lastResult.data && (
              <div className="mt-4 pt-4 border-t border-white/20 max-w-sm mx-auto text-xs space-y-1 text-left">
                <div className="flex justify-between">
                  <span className="text-white/60">Attendee:</span>
                  <span className="font-bold text-white">{lastResult.data.holder_name}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-white/60">Category:</span>
                  <span className="font-bold text-white">{lastResult.data.category}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-white/60">Ticket #:</span>
                  <span className="font-mono text-white">{lastResult.data.ticket_number}</span>
                </div>
                {lastResult.data.prior_check_in && (
                  <div className="flex justify-between text-red-300 font-semibold pt-1">
                    <span>Prior Scan:</span>
                    <span>{lastResult.data.prior_check_in.gate || "Gate"} ({lastResult.data.prior_check_in.staff_name || "Staff"})</span>
                  </div>
                )}
              </div>
            )}
          </div>
        )}

        {/* Scanner Barcode / Token Input */}
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 space-y-5 shadow-xl">
          <div className="flex justify-between items-center">
            <div>
              <h3 className="text-base font-bold text-white">Scan QR Token or Enter Ticket #</h3>
              <p className="text-xs text-slate-400 mt-0.5">
                Scan with a 2D barcode scanner or type the ticket code manually.
              </p>
            </div>
            <span className="text-2xl">📷</span>
          </div>

          <form onSubmit={handleManualSubmit} className="space-y-4">
            <div className="relative">
              <input
                ref={inputRef}
                type="text"
                autoFocus
                value={manualCode}
                onChange={(e) => setManualCode(e.target.value)}
                placeholder="Scan QR barcode or enter TCK-ORD-XXXX-XXXX-001..."
                className="w-full pl-4 pr-24 py-4 rounded-xl bg-slate-950 border-2 border-slate-700 text-white text-sm font-mono placeholder:font-sans focus:outline-none focus:border-emerald-500 shadow-inner"
              />
              <button
                type="submit"
                disabled={scanning || !manualCode.trim()}
                className="absolute right-2 top-2 bottom-2 px-5 rounded-lg text-xs font-bold bg-emerald-500 hover:bg-emerald-600 disabled:opacity-40 text-slate-950 transition-colors"
              >
                {scanning ? "Verifying..." : "Admit →"}
              </button>
            </div>
          </form>

          <div className="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800 text-[11px] text-slate-400 flex items-center justify-between">
            <span>💡 Camera scanners auto-submit upon scanning.</span>
            <span className="text-emerald-400 font-semibold">Online & Verified</span>
          </div>
        </div>

        {/* Recent Scans Feed */}
        {stats && stats.recent_check_ins.length > 0 && (
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4 shadow-xl">
            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">
              Recent Gate Admissions ({stats.recent_check_ins.length})
            </h3>

            <div className="divide-y divide-slate-800/80 text-xs">
              {stats.recent_check_ins.map((item, idx) => (
                <div key={idx} className="py-2.5 flex justify-between items-center">
                  <div>
                    <span className="font-bold text-white">{item.holder_name}</span>
                    <span className="text-slate-400 ml-2">({item.category})</span>
                    <div className="text-[10px] font-mono text-slate-400 mt-0.5">
                      {item.ticket_number} • {item.gate || "Main Gate"}
                    </div>
                  </div>
                  <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-950/80 text-emerald-400 border border-emerald-800">
                    Admitted
                  </span>
                </div>
              ))}
            </div>
          </div>
        )}
      </main>
    </div>
  );
}

