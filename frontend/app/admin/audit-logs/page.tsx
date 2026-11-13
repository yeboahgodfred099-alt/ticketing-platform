"use client";

import { useEffect, useState, useCallback } from "react";
import { apiClient } from "@/lib/api-client";
import { formatEventDate } from "@/lib/utils";
import { AuditLogItem, AuditLogPaginationMeta } from "@/types/audit";

export default function AdminAuditLogsPage() {
  const [logs, setLogs] = useState<AuditLogItem[]>([]);
  const [meta, setMeta] = useState<AuditLogPaginationMeta | null>(null);
  const [page, setPage] = useState<number>(1);
  const [actionFilter, setActionFilter] = useState<string>("");
  const [targetTypeFilter, setTargetTypeFilter] = useState<string>("");
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [activeLog, setActiveLog] = useState<AuditLogItem | null>(null);

  const fetchLogs = useCallback(async (currentPage: number) => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams();
      params.append("page", currentPage.toString());
      params.append("per_page", "25");
      if (actionFilter) params.append("action", actionFilter);
      if (targetTypeFilter) params.append("target_type", targetTypeFilter);

      const res = await apiClient<{
        data: AuditLogItem[];
        meta: AuditLogPaginationMeta;
      }>(`/admin/audit-logs?${params.toString()}`);

      setLogs(res.data);
      setMeta(res.meta);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Failed to load audit logs.";
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, [actionFilter, targetTypeFilter]);

  useEffect(() => {
    fetchLogs(page);
  }, [fetchLogs, page]);

  const handleFilterSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    fetchLogs(1);
  };

  const clearFilters = () => {
    setActionFilter("");
    setTargetTypeFilter("");
    setPage(1);
  };

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="pb-6 border-b border-slate-800">
        <h1 className="text-2xl font-bold text-white tracking-tight">Security Audit Logs</h1>
        <p className="text-xs text-slate-400 mt-1">
          Immutable, append-only administrative actions and state transition trails (OWASP ASVS L2).
        </p>
      </div>

      {/* Filter Bar */}
      <form
        onSubmit={handleFilterSubmit}
        className="p-4 rounded-xl bg-slate-900 border border-slate-800 flex flex-wrap items-center gap-3"
      >
        <div className="flex-1 min-w-[200px]">
          <input
            type="text"
            placeholder="Filter by action (e.g. event., admin., check_in)..."
            value={actionFilter}
            onChange={(e) => setActionFilter(e.target.value)}
            className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
          />
        </div>

        <div className="w-48">
          <input
            type="text"
            placeholder="Target Type (e.g. Event, Order)..."
            value={targetTypeFilter}
            onChange={(e) => setTargetTypeFilter(e.target.value)}
            className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
          />
        </div>

        <button
          type="submit"
          className="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-semibold text-xs transition-colors"
        >
          Search
        </button>

        {(actionFilter || targetTypeFilter) && (
          <button
            type="button"
            onClick={clearFilters}
            className="px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs transition-colors"
          >
            Clear
          </button>
        )}
      </form>

      {error && (
        <div className="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs">
          {error}
        </div>
      )}

      {/* Audit Log Table */}
      <div className="rounded-xl bg-slate-900 border border-slate-800 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs text-slate-300">
            <thead className="text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-800 bg-slate-950/60">
              <tr>
                <th className="py-3 px-4">Timestamp (Accra)</th>
                <th className="py-3 px-4">Action</th>
                <th className="py-3 px-4">Actor</th>
                <th className="py-3 px-4">Target</th>
                <th className="py-3 px-4">IP Address</th>
                <th className="py-3 px-4 text-right">Details</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800">
              {loading ? (
                <tr>
                  <td colSpan={6} className="py-8 text-center text-slate-500">
                    Loading audit trail...
                  </td>
                </tr>
              ) : logs.length === 0 ? (
                <tr>
                  <td colSpan={6} className="py-8 text-center text-slate-500">
                    No matching audit log records found.
                  </td>
                </tr>
              ) : (
                logs.map((log) => (
                  <tr key={log.id} className="hover:bg-slate-800/40">
                    <td className="py-3 px-4 text-slate-400 whitespace-nowrap">
                      {formatEventDate(log.created_at)}
                    </td>
                    <td className="py-3 px-4">
                      <span className="inline-block px-2 py-0.5 rounded text-[11px] font-mono font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        {log.action}
                      </span>
                    </td>
                    <td className="py-3 px-4">
                      <div className="font-semibold text-white">{log.admin_name}</div>
                      {log.admin_email && (
                        <div className="text-[10px] text-slate-500">{log.admin_email}</div>
                      )}
                    </td>
                    <td className="py-3 px-4">
                      {log.target_type ? (
                        <div>
                          <span className="font-medium text-slate-300">{log.target_type}</span>
                          {log.target_id && (
                            <span className="block text-[10px] font-mono text-slate-500 truncate max-w-[140px]">
                              {log.target_id}
                            </span>
                          )}
                        </div>
                      ) : (
                        <span className="text-slate-600">—</span>
                      )}
                    </td>
                    <td className="py-3 px-4 text-slate-400 font-mono text-[11px]">
                      {log.ip_address || "—"}
                    </td>
                    <td className="py-3 px-4 text-right">
                      <button
                        onClick={() => setActiveLog(log)}
                        className="text-xs text-emerald-400 hover:text-emerald-300 font-medium"
                      >
                        Inspect
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {meta && meta.last_page > 1 && (
          <div className="p-4 border-t border-slate-800 flex justify-between items-center text-xs text-slate-400">
            <div>
              Showing {meta.from ?? 0} to {meta.to ?? 0} of {meta.total} records
            </div>
            <div className="flex gap-2">
              <button
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                className="px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700 disabled:opacity-40 disabled:hover:bg-slate-800 text-slate-200"
              >
                Previous
              </button>
              <span className="px-3 py-1.5 text-slate-400">
                Page {meta.current_page} of {meta.last_page}
              </span>
              <button
                disabled={page >= meta.last_page}
                onClick={() => setPage((p) => p + 1)}
                className="px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700 disabled:opacity-40 disabled:hover:bg-slate-800 text-slate-200"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Inspect Modal */}
      {activeLog && (
        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-xl w-full p-6 space-y-4">
            <div className="flex justify-between items-start">
              <div>
                <h3 className="text-base font-bold text-white">Audit Entry Details</h3>
                <p className="text-xs text-slate-400 font-mono mt-0.5">{activeLog.id}</p>
              </div>
              <button
                onClick={() => setActiveLog(null)}
                className="text-slate-400 hover:text-white text-base"
              >
                ✕
              </button>
            </div>

            <div className="space-y-2 text-xs">
              <div className="flex justify-between py-1 border-b border-slate-800">
                <span className="text-slate-400">Action:</span>
                <span className="font-semibold text-emerald-400">{activeLog.action}</span>
              </div>
              <div className="flex justify-between py-1 border-b border-slate-800">
                <span className="text-slate-400">Actor:</span>
                <span className="text-white">
                  {activeLog.admin_name} ({activeLog.admin_email || "N/A"})
                </span>
              </div>
              <div className="flex justify-between py-1 border-b border-slate-800">
                <span className="text-slate-400">Timestamp:</span>
                <span className="text-white">{formatEventDate(activeLog.created_at)}</span>
              </div>
              <div className="flex justify-between py-1 border-b border-slate-800">
                <span className="text-slate-400">IP Address:</span>
                <span className="font-mono text-white">{activeLog.ip_address || "—"}</span>
              </div>
              <div className="py-1 border-b border-slate-800">
                <span className="text-slate-400 block mb-1">User Agent:</span>
                <span className="font-mono text-[11px] text-slate-300 break-all">
                  {activeLog.user_agent || "—"}
                </span>
              </div>
            </div>

            <div>
              <span className="text-xs text-slate-400 font-semibold block mb-2">
                Sanitized Metadata:
              </span>
              <pre className="p-3 rounded-lg bg-slate-950 border border-slate-800 text-[11px] font-mono text-slate-200 overflow-x-auto max-h-56">
                {JSON.stringify(activeLog.metadata, null, 2)}
              </pre>
            </div>

            <div className="flex justify-end pt-2">
              <button
                onClick={() => setActiveLog(null)}
                className="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs text-white font-semibold transition-colors"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
