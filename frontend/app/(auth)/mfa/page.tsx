"use client";

import { Suspense, useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import Link from "next/link";
import { apiClient } from "@/lib/api-client";
import { MfaConfirmResponse, MfaSetupResponse } from "@/lib/auth";

function MfaContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const isSetupMode = searchParams.get("setup") === "true";

  const [code, setCode] = useState("");
  const [loading, setLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Setup mode states
  const [setupData, setSetupData] = useState<MfaSetupResponse | null>(null);
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);

  useEffect(() => {
    if (isSetupMode) {
      apiClient<MfaSetupResponse>("/auth/mfa/setup", { method: "POST" })
        .then(setSetupData)
        .catch((err: Error) => setErrorMessage(err.message));
    }
  }, [isSetupMode]);

  const handleVerify = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);
    setLoading(true);

    try {
      if (isSetupMode) {
        const response = await apiClient<MfaConfirmResponse>("/auth/mfa/confirm", {
          method: "POST",
          body: JSON.stringify({ code }),
        });

        setRecoveryCodes(response.recovery_codes);
      } else {
        await apiClient("/auth/mfa/verify", {
          method: "POST",
          body: JSON.stringify({ code }),
        });

        router.push("/admin/dashboard");
      }
    } catch (err: unknown) {
      if (err instanceof Error) {
        setErrorMessage(err.message);
      } else {
        setErrorMessage("Verification failed.");
      }
    } finally {
      setLoading(false);
    }
  };

  if (recoveryCodes) {
    return (
      <div className="bg-slate-900 border border-slate-800 p-8 shadow-xl rounded-2xl max-w-lg mx-auto">
        <h3 className="text-xl font-bold text-white mb-2">Save Your Recovery Codes</h3>
        <p className="text-xs text-slate-400 mb-6 leading-relaxed">
          Store these emergency codes in a secure password manager. If you lose access to your authenticator app, each code can be used once to access your account.
        </p>

        <div className="grid grid-cols-2 gap-2.5 p-4 rounded-xl bg-slate-950 border border-slate-800 font-mono text-sm text-emerald-400 mb-6">
          {recoveryCodes.map((rc, idx) => (
            <div key={idx} className="p-1.5 text-center bg-slate-900 rounded border border-slate-800">
              {rc}
            </div>
          ))}
        </div>

        <button
          onClick={() => router.push("/admin/dashboard")}
          className="w-full py-2.5 px-4 rounded-lg text-sm font-medium bg-emerald-500 hover:bg-emerald-600 text-slate-950 transition-colors shadow-lg shadow-emerald-500/20"
        >
          I Have Saved My Recovery Codes →
        </button>
      </div>
    );
  }

  return (
    <div className="bg-slate-900 border border-slate-800 py-8 px-4 shadow-xl rounded-2xl sm:px-10 max-w-md mx-auto">
      <div className="text-center mb-6">
        <h3 className="text-xl font-semibold tracking-tight text-white mb-1">
          {isSetupMode ? "Setup Two-Factor Authentication" : "Two-Factor Verification"}
        </h3>
        <p className="text-xs text-slate-400">
          {isSetupMode
            ? "Scan the QR code with your authenticator app and enter the code below."
            : "Enter the 6-digit code from your authenticator app or an 8-character recovery code."}
        </p>
      </div>

      {isSetupMode && setupData && (
        <div className="mb-6 p-4 rounded-xl bg-slate-950 border border-slate-800 text-center">
          <div className="p-3 bg-white inline-block rounded-lg mb-3">
            {/* Display setup instructions & secret key */}
            <div className="text-slate-900 text-xs font-mono font-bold tracking-widest break-all max-w-[200px]">
              {setupData.secret}
            </div>
          </div>
          <p className="text-[11px] text-slate-400">
            Manual entry key: <span className="font-mono text-emerald-400">{setupData.secret}</span>
          </p>
        </div>
      )}

      {errorMessage && (
        <div className="mb-6 p-3.5 rounded-lg bg-red-950/60 border border-red-800/80 text-red-300 text-sm flex items-start gap-2">
          <span className="text-base font-bold">!</span>
          <div>{errorMessage}</div>
        </div>
      )}

      <form className="space-y-5" onSubmit={handleVerify}>
        <div>
          <label
            htmlFor="code"
            className="block text-xs font-medium uppercase tracking-wider text-slate-400 mb-1.5"
          >
            Verification Code
          </label>
          <input
            id="code"
            name="code"
            type="text"
            required
            autoFocus
            autoComplete="one-time-code"
            value={code}
            onChange={(e) => setCode(e.target.value.trim())}
            placeholder="123456"
            className="w-full text-center tracking-[0.3em] font-mono text-xl py-2.5 rounded-lg bg-slate-950 border border-slate-800 text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition-colors"
          />
        </div>

        <button
          type="submit"
          disabled={loading || code.length < 6}
          className="w-full flex justify-center py-2.5 px-4 rounded-lg text-sm font-medium bg-emerald-500 hover:bg-emerald-600 disabled:bg-slate-800 disabled:text-slate-400 text-slate-950 transition-colors shadow-lg shadow-emerald-500/20"
        >
          {loading ? "Verifying..." : isSetupMode ? "Complete Setup" : "Verify & Continue"}
        </button>
      </form>

      <div className="mt-6 border-t border-slate-800/80 pt-4 text-center">
        <Link
          href="/login"
          className="text-xs text-slate-400 hover:text-slate-300 transition-colors"
        >
          ← Cancel and return to sign in
        </Link>
      </div>
    </div>
  );
}

export default function MfaPage() {
  return (
    <div className="min-h-screen bg-slate-950 flex flex-col justify-center py-12 sm:px-6 lg:px-8 text-slate-100">
      <Suspense fallback={<div className="text-center text-slate-400 text-sm">Loading MFA portal...</div>}>
        <MfaContent />
      </Suspense>
    </div>
  );
}
