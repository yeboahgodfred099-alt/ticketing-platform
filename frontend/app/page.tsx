import Link from "next/link";
import { formatMoney } from "@/lib/utils";
import { GHANA_MOBILE_MONEY_NETWORKS } from "@/lib/constants";

export default function Home() {
  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
      {/* Navigation */}
      <header className="border-b border-slate-800 bg-slate-900/80 backdrop-blur sticky top-0 z-50">
        <div className="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <span className="text-xl font-bold tracking-tight text-white">
              ClevaWave <span className="text-emerald-400">Tickets</span>
            </span>
          </div>
          <nav className="flex items-center gap-4">
            <Link
              href="/admin/login"
              className="text-sm text-slate-400 hover:text-white transition-colors"
            >
              Organizer Login
            </Link>
          </nav>
        </div>
      </header>

      {/* Hero Section */}
      <main className="flex-1 max-w-6xl mx-auto px-4 py-16 flex flex-col justify-center">
        <div className="max-w-3xl">
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium bg-emerald-950/60 border border-emerald-800 text-emerald-400 mb-6">
            <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
            Live in Ghana — No Account Required
          </div>
          <h1 className="text-4xl sm:text-6xl font-extrabold tracking-tight text-white mb-6 leading-tight">
            Seamless Event Tickets with{" "}
            <span className="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-teal-300">
              Mobile Money
            </span>
          </h1>
          <p className="text-lg sm:text-xl text-slate-400 mb-8 leading-relaxed">
            Discover premier Ghanaian events. Select tickets, enter your contact info, and approve your payment in seconds.
          </p>

          <div className="flex flex-wrap gap-4 items-center mb-12">
            <a
              href="#upcoming"
              className="px-6 py-3 rounded-lg font-medium bg-emerald-500 hover:bg-emerald-600 text-slate-950 transition-colors shadow-lg shadow-emerald-500/20"
            >
              Browse Events
            </a>
            <Link
              href="/api/health"
              className="px-6 py-3 rounded-lg font-medium border border-slate-700 hover:bg-slate-900 text-slate-300 transition-colors text-sm"
              target="_blank"
            >
              System Health Check (v1.0.0)
            </Link>
          </div>

          {/* Supported Mobile Money Networks */}
          <div className="border-t border-slate-800/80 pt-8">
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-4">
              Supported Payment Networks
            </p>
            <div className="flex flex-wrap gap-3">
              {GHANA_MOBILE_MONEY_NETWORKS.map((network) => (
                <div
                  key={network.code}
                  className="flex items-center gap-2 px-3.5 py-1.5 rounded-md bg-slate-900 border border-slate-800 text-xs font-medium text-slate-300"
                >
                  <span className={`w-2 h-2 rounded-full ${network.badgeColor}`} />
                  {network.name}
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Feature Cards */}
        <section id="upcoming" className="mt-20 grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="p-6 rounded-xl bg-slate-900/60 border border-slate-800/80">
            <div className="text-emerald-400 text-2xl font-bold mb-2">Instant Delivery</div>
            <p className="text-sm text-slate-400">
              Receive opaque cryptographic QR tickets immediately via email and on your confirmation screen.
            </p>
          </div>
          <div className="p-6 rounded-xl bg-slate-900/60 border border-slate-800/80">
            <div className="text-emerald-400 text-2xl font-bold mb-2">Zero Account Friction</div>
            <p className="text-sm text-slate-400">
              Never remember passwords. Retrieve tickets anytime with high-entropy access links.
            </p>
          </div>
          <div className="p-6 rounded-xl bg-slate-900/60 border border-slate-800/80">
            <div className="text-emerald-400 text-2xl font-bold mb-2">No Hidden Fees</div>
            <p className="text-sm text-slate-400">
              What you see is what you pay: e.g. {formatMoney(5000)} is exactly {formatMoney(5000)}. No surprise surcharges.
            </p>
          </div>
        </section>
      </main>

      {/* Footer */}
      <footer className="border-t border-slate-800/80 py-8 bg-slate-950 text-slate-400 text-xs text-center">
        <div className="max-w-6xl mx-auto px-4 flex flex-col sm:flex-row justify-between items-center gap-4">
          <div>© 2026 ClevaWave Ticketing. Built for Ghana.</div>
          <div className="flex gap-4">
            <Link href="/privacy" className="hover:text-slate-300">Privacy Notice</Link>
            <Link href="/terms" className="hover:text-slate-300">Terms of Service</Link>
            <a href="mailto:tickets@clevawave.com" className="hover:text-slate-300">Contact Support</a>
          </div>
        </div>
      </footer>
    </div>
  );
}
