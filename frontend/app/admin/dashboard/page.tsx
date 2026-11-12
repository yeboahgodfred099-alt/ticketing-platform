"use client";

import Link from "next/link";
import { useAuth } from "@/hooks/useAuth";

export default function AdminDashboardPage() {
  const { admin, hasRole } = useAuth(true);

  return (
    <div className="space-y-8">
      {/* Welcome Banner */}
      <div className="p-6 rounded-2xl bg-gradient-to-r from-slate-900 to-slate-900/60 border border-slate-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">
            Welcome back, {admin?.name || "Administrator"}
          </h1>
          <p className="text-xs text-slate-400 mt-1">
            System Status: <span className="text-emerald-400 font-semibold">Active & Operational</span> • Roles: {admin?.roles.map((r) => r.name).join(", ")}
          </p>
        </div>

        <div className="flex gap-3">
          <Link
            href="/admin/events"
            className="px-4 py-2 rounded-lg text-xs font-semibold bg-emerald-500 hover:bg-emerald-600 text-slate-950 transition-colors shadow-lg shadow-emerald-500/20"
          >
            + Create New Event
          </Link>
        </div>
      </div>

      {/* Metrics Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div className="p-5 rounded-xl bg-slate-900 border border-slate-800">
          <div className="text-xs font-semibold uppercase tracking-wider text-slate-400">
            Active Events
          </div>
          <div className="text-2xl font-bold text-white mt-2">1</div>
          <div className="text-[11px] text-emerald-400 mt-1">Accra Summit 2026 published</div>
        </div>

        <div className="p-5 rounded-xl bg-slate-900 border border-slate-800">
          <div className="text-xs font-semibold uppercase tracking-wider text-slate-400">
            Tickets Sold
          </div>
          <div className="text-2xl font-bold text-white mt-2">0</div>
          <div className="text-[11px] text-slate-400 mt-1">750 total available</div>
        </div>

        <div className="p-5 rounded-xl bg-slate-900 border border-slate-800">
          <div className="text-xs font-semibold uppercase tracking-wider text-slate-400">
            Gross Paid Revenue
          </div>
          <div className="text-2xl font-bold text-white mt-2">GH₵ 0.00</div>
          <div className="text-[11px] text-slate-400 mt-1">Excludes pending orders</div>
        </div>

        <div className="p-5 rounded-xl bg-slate-900 border border-slate-800">
          <div className="text-xs font-semibold uppercase tracking-wider text-slate-400">
            Security & MFA
          </div>
          <div className="text-2xl font-bold text-white mt-2">
            {admin?.has_mfa ? "Protected" : "Enforced"}
          </div>
          <div className="text-[11px] text-emerald-400 mt-1">
            {admin?.has_mfa ? "✓ 2FA Active" : "Pending setup"}
          </div>
        </div>
      </div>

      {/* Security & Operational Overview */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="p-6 rounded-xl bg-slate-900 border border-slate-800">
          <h2 className="text-base font-bold text-white mb-4">Security Baseline (OWASP ASVS L2)</h2>
          <ul className="space-y-3 text-xs text-slate-300">
            <li className="flex items-center gap-2">
              <span className="text-emerald-400">✓</span>
              Stateful session authentication with HttpOnly cookies & CSRF protection
            </li>
            <li className="flex items-center gap-2">
              <span className="text-emerald-400">✓</span>
              Mandatory Multi-Factor Authentication (TOTP) for privileged administrators
            </li>
            <li className="flex items-center gap-2">
              <span className="text-emerald-400">✓</span>
              Immutable append-only audit trail logging for all sensitive operations
            </li>
            <li className="flex items-center gap-2">
              <span className="text-emerald-400">✓</span>
              Paystack Mobile Money server-side API integration (secret keys protected)
            </li>
          </ul>
        </div>

        <div className="p-6 rounded-xl bg-slate-900 border border-slate-800">
          <h2 className="text-base font-bold text-white mb-4">Quick Navigation</h2>
          <div className="grid grid-cols-2 gap-3 text-xs">
            <Link
              href="/admin/events"
              className="p-3 rounded-lg bg-slate-950 border border-slate-800 hover:border-slate-700 text-slate-200 block"
            >
              <div className="font-semibold text-white">Event Catalog</div>
              <div className="text-[11px] text-slate-400 mt-0.5">Manage events & tickets</div>
            </Link>

            <Link
              href="/admin/orders"
              className="p-3 rounded-lg bg-slate-950 border border-slate-800 hover:border-slate-700 text-slate-200 block"
            >
              <div className="font-semibold text-white">Orders & Sales</div>
              <div className="text-[11px] text-slate-400 mt-0.5">View purchase orders</div>
            </Link>

            <Link
              href="/admin/payments"
              className="p-3 rounded-lg bg-slate-950 border border-slate-800 hover:border-slate-700 text-slate-200 block"
            >
              <div className="font-semibold text-white">Payments</div>
              <div className="text-[11px] text-slate-400 mt-0.5">Reconciliation & attempts</div>
            </Link>

            {hasRole("Super Admin") && (
              <Link
                href="/admin/users"
                className="p-3 rounded-lg bg-slate-950 border border-slate-800 hover:border-slate-700 text-slate-200 block"
              >
                <div className="font-semibold text-white">Administrators</div>
                <div className="text-[11px] text-slate-400 mt-0.5">Manage team & roles</div>
              </Link>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
