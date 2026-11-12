"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { AdminUser } from "@/lib/auth";

interface AdminSidebarProps {
  admin: AdminUser | null;
}

export function AdminSidebar({ admin }: AdminSidebarProps) {
  const pathname = usePathname();

  const isSuperAdmin = admin?.roles.some((r) => r.name === "Super Admin") ?? false;
  const isFinance = admin?.roles.some((r) => r.name === "Finance Manager" || r.name === "Super Admin") ?? false;

  const navigation = [
    { name: "Dashboard", href: "/admin/dashboard", icon: "📊" },
    { name: "Events", href: "/admin/events", icon: "🎟️" },
    { name: "Orders", href: "/admin/orders", icon: "📦" },
    { name: "Payments", href: "/admin/payments", icon: "💳" },
    { name: "Attendees", href: "/admin/attendees", icon: "👥" },
    ...(isFinance ? [{ name: "Reports", href: "/admin/reports", icon: "📈" }] : []),
    ...(isSuperAdmin
      ? [
          { name: "Audit Logs", href: "/admin/audit-logs", icon: "📋" },
          { name: "Administrators", href: "/admin/users", icon: "🛡️" },
          { name: "Settings", href: "/admin/settings", icon: "⚙️" },
        ]
      : []),
  ];

  return (
    <aside className="w-64 bg-slate-900 border-r border-slate-800 flex flex-col min-h-screen">
      <div className="h-16 flex items-center px-6 border-b border-slate-800">
        <Link href="/admin/dashboard" className="text-lg font-bold text-white tracking-tight">
          ClevaWave <span className="text-emerald-400">Admin</span>
        </Link>
      </div>

      <nav className="flex-1 px-4 py-6 space-y-1">
        {navigation.map((item) => {
          const isActive = pathname === item.href || pathname.startsWith(`${item.href}/`);
          return (
            <Link
              key={item.name}
              href={item.href}
              className={`flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                isActive
                  ? "bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"
                  : "text-slate-400 hover:text-slate-200 hover:bg-slate-800/60"
              }`}
            >
              <span className="text-base">{item.icon}</span>
              {item.name}
            </Link>
          );
        })}
      </nav>

      <div className="p-4 border-t border-slate-800">
        <div className="flex items-center gap-3 px-2 py-1.5">
          <div className="w-8 h-8 rounded-full bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 flex items-center justify-center font-bold text-xs">
            {admin?.name?.charAt(0) || "A"}
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-xs font-semibold text-white truncate">{admin?.name}</p>
            <p className="text-[11px] text-slate-400 truncate">
              {admin?.roles?.[0]?.name || "Staff"}
            </p>
          </div>
        </div>
      </div>
    </aside>
  );
}

