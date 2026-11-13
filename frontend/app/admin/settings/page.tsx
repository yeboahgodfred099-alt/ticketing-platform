"use client";

export default function AdminSettingsPage() {
  return (
    <div className="space-y-8 max-w-4xl">
      {/* Header */}
      <div className="pb-6 border-b border-slate-800">
        <h1 className="text-2xl font-bold text-white tracking-tight">System Settings & Policies</h1>
        <p className="text-xs text-slate-400 mt-1">
          Authoritative operational parameters, Paystack fee configurations, and security baselines.
        </p>
      </div>

      {/* Operational Policy Cards */}
      <div className="space-y-6">
        {/* Payment Policy */}
        <div className="p-6 rounded-xl bg-slate-900 border border-slate-800 space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-sm font-bold text-white">Payment & Fee Architecture</h2>
              <p className="text-xs text-slate-400 mt-0.5">
                Rules governing Paystack Ghana Mobile Money transactions.
              </p>
            </div>
            <span className="px-2.5 py-1 rounded text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
              Absorbed Policy Active
            </span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 text-xs text-slate-300">
            <div className="p-3.5 rounded-lg bg-slate-950 border border-slate-800">
              <span className="text-slate-400 block mb-1">Fee Model</span>
              <span className="font-semibold text-white">Organizer Absorbs 1.95% Paystack Fee</span>
            </div>
            <div className="p-3.5 rounded-lg bg-slate-950 border border-slate-800">
              <span className="text-slate-400 block mb-1">Customer Surcharge</span>
              <span className="font-semibold text-emerald-400">GH₵ 0.00 (Customer pays pure face value)</span>
            </div>
            <div className="p-3.5 rounded-lg bg-slate-950 border border-slate-800">
              <span className="text-slate-400 block mb-1">Supported Currency</span>
              <span className="font-semibold text-white">GHS (Ghana Cedi in Pesewas)</span>
            </div>
            <div className="p-3.5 rounded-lg bg-slate-950 border border-slate-800">
              <span className="text-slate-400 block mb-1">USSD Timeout Window</span>
              <span className="font-semibold text-white">180 Seconds</span>
            </div>
          </div>
        </div>

        {/* Inventory Reservation Policy */}
        <div className="p-6 rounded-xl bg-slate-900 border border-slate-800 space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-sm font-bold text-white">Inventory Reservation Time-to-Live (TTL)</h2>
              <p className="text-xs text-slate-400 mt-0.5">
                Temporary holds placed during guest checkout to prevent overselling.
              </p>
            </div>
            <span className="px-2.5 py-1 rounded text-[11px] font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/30">
              15 Min Window
            </span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 text-xs text-slate-300">
            <div className="p-3.5 rounded-lg bg-slate-950 border border-slate-800">
              <span className="text-slate-400 block mb-1">Reservation TTL</span>
              <span className="font-semibold text-white">15 Minutes</span>
            </div>
            <div className="p-3.5 rounded-lg bg-slate-950 border border-slate-800">
              <span className="text-slate-400 block mb-1">Expiration Scheduler</span>
              <span className="font-semibold text-white">Runs every 60 seconds (artisan reservations:expire)</span>
            </div>
          </div>
        </div>

        {/* Security Baseline (OWASP ASVS L2) */}
        <div className="p-6 rounded-xl bg-slate-900 border border-slate-800 space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-sm font-bold text-white">Security & Cryptography Baseline</h2>
              <p className="text-xs text-slate-400 mt-0.5">
                Cryptographic controls per OWASP ASVS Level 2 verification.
              </p>
            </div>
            <span className="px-2.5 py-1 rounded text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
              Enforced
            </span>
          </div>

          <ul className="space-y-3 text-xs text-slate-300">
            <li className="flex items-center gap-2.5 p-3 rounded-lg bg-slate-950 border border-slate-800">
              <span className="text-emerald-400 text-sm">🔒</span>
              <span>QR Tokens: 256-bit cryptographically secure random bytes; stored as SHA-256 hashes at rest</span>
            </li>
            <li className="flex items-center gap-2.5 p-3 rounded-lg bg-slate-950 border border-slate-800">
              <span className="text-emerald-400 text-sm">🛡️</span>
              <span>Webhooks: HMAC-SHA512 raw-body signature validation before JSON parsing</span>
            </li>
            <li className="flex items-center gap-2.5 p-3 rounded-lg bg-slate-950 border border-slate-800">
              <span className="text-emerald-400 text-sm">🔑</span>
              <span>Authentication: Argon2id password hashing + Time-Based One-Time Passwords (TOTP MFA)</span>
            </li>
            <li className="flex items-center gap-2.5 p-3 rounded-lg bg-slate-950 border border-slate-800">
              <span className="text-emerald-400 text-sm">📜</span>
              <span>Audit Trails: Append-only immutable administrative logs scrubbing all secret keys</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  );
}

