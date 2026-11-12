"use client";

import { useEffect, useState, useRef, Suspense, use } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import Link from "next/link";
import { apiClient, ApiError } from "@/lib/api-client";
import { OrderData, PaymentInitiateResponse, PaymentStatusResponse } from "@/types/order";
import { formatMoney } from "@/lib/utils";
import { GHANA_MOBILE_MONEY_NETWORKS, MobileMoneyProviderCode } from "@/lib/constants";

interface PaymentPageProps {
  params: Promise<{ orderNumber: string }>;
}

function PaymentContent({ params }: PaymentPageProps) {
  const { orderNumber } = use(params);
  const searchParams = useSearchParams();
  const router = useRouter();

  // Token resolution: from query string or sessionStorage
  const [token, setToken] = useState<string | null>(null);
  const [order, setOrder] = useState<OrderData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Payment form and status state
  const [selectedProvider, setSelectedProvider] = useState<MobileMoneyProviderCode>("mtn");
  const [phone, setPhone] = useState("");
  const [initiating, setInitiating] = useState(false);
  const [paymentData, setPaymentData] = useState<PaymentInitiateResponse | null>(null);
  const [paymentStatus, setPaymentStatus] = useState<PaymentStatusResponse | null>(null);

  // OTP State
  const [otp, setOtp] = useState("");
  const [submittingOtp, setSubmittingOtp] = useState(false);
  const [otpError, setOtpError] = useState<string | null>(null);

  // Countdown timer for prompt approval (180s)
  const [countdown, setCountdown] = useState<number>(180);
  const pollingIntervalRef = useRef<NodeJS.Timeout | null>(null);
  const timerIntervalRef = useRef<NodeJS.Timeout | null>(null);

  // 1. Resolve access token on mount
  useEffect(() => {
    const queryToken = searchParams.get("token");
    let resolvedToken = queryToken;

    if (!resolvedToken && typeof window !== "undefined") {
      resolvedToken = sessionStorage.getItem(`order_token_${orderNumber}`);
    }

    if (!resolvedToken) {
      setError("Order access token is missing or expired. Please check your confirmation link.");
      setLoading(false);
      return;
    }

    setToken(resolvedToken);

    // Fetch order details with token in header
    apiClient<{ data: OrderData }>(`/api/v1/public/orders/${orderNumber}`, {
      headers: {
        "X-Order-Token": resolvedToken,
      },
    })
      .then((res) => {
        setOrder(res.data);
        setPhone(res.data.customer_phone);
        setLoading(false);
      })
      .catch((err) => {
        setError(err.message || "Failed to load order for payment.");
        setLoading(false);
      });
  }, [orderNumber, searchParams]);

  // 2. Poll payment status when payment reference exists and is not final
  useEffect(() => {
    const reference = paymentData?.reference || paymentStatus?.reference;
    if (!reference || paymentStatus?.is_final) {
      if (pollingIntervalRef.current) {
        clearInterval(pollingIntervalRef.current);
      }
      return;
    }

    const poll = async () => {
      try {
        const res = await apiClient<{ data: PaymentStatusResponse }>(
          `/api/v1/public/payments/${reference}/status`
        );
        const current = res.data;
        setPaymentStatus(current);

        if (current.status === "success") {
          // Clear intervals upon success
          if (pollingIntervalRef.current) clearInterval(pollingIntervalRef.current);
          if (timerIntervalRef.current) clearInterval(timerIntervalRef.current);
        }
      } catch {
        // Continue polling on transient network hiccup
      }
    };

    pollingIntervalRef.current = setInterval(poll, 3000);

    return () => {
      if (pollingIntervalRef.current) clearInterval(pollingIntervalRef.current);
    };
  }, [paymentData?.reference, paymentStatus?.reference, paymentStatus?.is_final]);

  // 3. Countdown timer when prompt is active
  useEffect(() => {
    if (paymentData?.status === "pending" && !paymentStatus?.is_final) {
      setCountdown(paymentData.timeout_seconds || 180);

      timerIntervalRef.current = setInterval(() => {
        setCountdown((prev) => {
          if (prev <= 1) {
            if (timerIntervalRef.current) clearInterval(timerIntervalRef.current);
            return 0;
          }
          return prev - 1;
        });
      }, 1000);
    }

    return () => {
      if (timerIntervalRef.current) clearInterval(timerIntervalRef.current);
    };
  }, [paymentData?.status, paymentData?.timeout_seconds, paymentStatus?.is_final]);

  // 4. Initiate Payment
  const handleInitiatePayment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token || !order) return;

    setInitiating(true);
    setError(null);

    try {
      const res = await apiClient<{ data: PaymentInitiateResponse }>(
        "/api/v1/public/payments/initiate",
        {
          method: "POST",
          headers: {
            "X-Order-Token": token,
          },
          body: JSON.stringify({
            order_number: order.order_number,
            provider: selectedProvider,
            phone: phone.trim(),
          }),
        }
      );

      setPaymentData(res.data);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Failed to initiate Mobile Money payment. Please try again.");
      }
    } finally {
      setInitiating(false);
    }
  };

  // 5. Submit OTP Continuation
  const handleSubmitOtp = async (e: React.FormEvent) => {
    e.preventDefault();
    const reference = paymentData?.reference;
    if (!token || !reference || !otp.trim()) return;

    setSubmittingOtp(true);
    setOtpError(null);

    try {
      const res = await apiClient<{ data: PaymentInitiateResponse }>(
        "/api/v1/public/payments/submit-otp",
        {
          method: "POST",
          headers: {
            "X-Order-Token": token,
          },
          body: JSON.stringify({
            reference,
            otp: otp.trim(),
          }),
        }
      );

      setPaymentData(res.data);
      setOtp("");
    } catch (err) {
      if (err instanceof ApiError) {
        setOtpError(err.message);
      } else {
        setOtpError("Failed to submit OTP. Please check the code and retry.");
      }
    } finally {
      setSubmittingOtp(false);
    }
  };

  const handleResetPayment = () => {
    setPaymentData(null);
    setPaymentStatus(null);
    setError(null);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-8">
        <div className="text-center space-y-3">
          <div className="text-3xl animate-spin">⏳</div>
          <p className="text-xs text-slate-400">Loading order payment details...</p>
        </div>
      </div>
    );
  }

  if (error && !order) {
    return (
      <div className="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-8">
        <div className="max-w-md w-full p-8 rounded-2xl bg-slate-900 border border-slate-800 text-center space-y-4 shadow-xl">
          <div className="text-4xl">⚠️</div>
          <h2 className="text-xl font-bold text-white">Payment Session Unavailable</h2>
          <p className="text-xs text-slate-400 leading-relaxed">{error}</p>
          <Link
            href="/"
            className="px-5 py-2.5 rounded-lg text-xs font-semibold bg-emerald-500 text-slate-950 inline-block transition-colors"
          >
            ← Browse Events
          </Link>
        </div>
      </div>
    );
  }

  if (!order) return null;

  const currentStatus = paymentStatus?.status || paymentData?.status || "initial";
  const isPaid = currentStatus === "success" || order.status === "paid";
  const isFailed = currentStatus === "failed";
  const isExpired = currentStatus === "expired" || countdown === 0;
  const isPending = currentStatus === "pending" && !isExpired && !isPaid;
  const isOtpRequired = currentStatus === "requires_action";

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
      {/* Header */}
      <header className="border-b border-slate-800 bg-slate-900/80 backdrop-blur sticky top-0 z-50">
        <div className="max-w-4xl mx-auto px-4 h-16 flex items-center justify-between">
          <Link href="/" className="text-xl font-bold tracking-tight text-white">
            ClevaWave <span className="text-emerald-400">Tickets</span>
          </Link>
          <div className="flex items-center gap-2 text-xs text-slate-400 font-mono">
            <span>Order #{order.order_number}</span>
          </div>
        </div>
      </header>

      <main className="flex-1 max-w-xl mx-auto px-4 py-12 w-full flex flex-col justify-center">
        {/* Error Alert */}
        {error && (
          <div className="p-4 rounded-xl bg-red-950/60 border border-red-800 text-xs text-red-300 mb-6">
            {error}
          </div>
        )}

        {/* State 1: Payment Success */}
        {isPaid ? (
          <div className="p-8 sm:p-10 rounded-2xl bg-slate-900 border border-emerald-500/30 text-center space-y-6 shadow-2xl">
            <div className="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 text-3xl flex items-center justify-center mx-auto border border-emerald-500/40">
              ✓
            </div>
            <div>
              <span className="text-[11px] font-bold uppercase tracking-wider text-emerald-400">
                Payment Confirmed
              </span>
              <h2 className="text-2xl sm:text-3xl font-extrabold text-white mt-1">
                You&apos;re Going to the Event!
              </h2>
              <p className="text-xs text-slate-400 mt-2 max-w-sm mx-auto">
                Your payment of {formatMoney(order.total_amount_minor)} was received. Cryptographic QR tickets have been generated.
              </p>
            </div>

            <div className="p-4 rounded-xl bg-slate-950/70 border border-slate-800 text-xs text-left space-y-2">
              <div className="flex justify-between">
                <span className="text-slate-400">Event:</span>
                <span className="text-white font-semibold">{order.event?.name}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-400">Order Number:</span>
                <span className="text-white font-mono">{order.order_number}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-400">Attendee:</span>
                <span className="text-white">{order.customer_name}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-400">Delivered To:</span>
                <span className="text-white">{order.customer_email}</span>
              </div>
            </div>

            <div className="pt-2 flex flex-col gap-3">
              <button
                type="button"
                onClick={() => router.push(`/tickets/${token}`)}
                className="w-full py-3.5 rounded-xl text-sm font-bold bg-emerald-500 hover:bg-emerald-600 text-slate-950 transition-colors shadow-lg shadow-emerald-500/20"
              >
                View My Tickets (QR Code) 🎟️
              </button>
              <Link
                href="/"
                className="text-xs text-slate-400 hover:text-white transition-colors py-2"
              >
                Back to Home
              </Link>
            </div>
          </div>
        ) : isPending ? (
          /* State 2: Awaiting MoMo USSD Approval */
          <div className="p-8 sm:p-10 rounded-2xl bg-slate-900 border border-slate-800 text-center space-y-6 shadow-2xl">
            <div className="relative w-16 h-16 mx-auto flex items-center justify-center">
              <span className="absolute inset-0 rounded-full bg-emerald-500/20 animate-ping" />
              <div className="w-14 h-14 rounded-full bg-slate-800 border border-emerald-500/40 text-2xl flex items-center justify-center text-emerald-400">
                📲
              </div>
            </div>

            <div>
              <span className="text-[11px] font-bold uppercase tracking-wider text-emerald-400">
                USSD Approval Prompt Sent
              </span>
              <h2 className="text-2xl font-bold text-white mt-1">Approve on Your Phone</h2>
              <div className="text-xs text-slate-300 mt-3 p-4 rounded-xl bg-slate-950/80 border border-slate-800/80 text-left leading-relaxed whitespace-pre-line">
                {paymentData?.display_text || paymentStatus?.display_text}
              </div>
            </div>

            {/* Countdown timer */}
            <div className="p-3.5 rounded-xl bg-slate-950/40 border border-slate-800 flex items-center justify-between text-xs">
              <span className="text-slate-400">Prompt Expiry:</span>
              <span className="text-emerald-400 font-mono font-bold text-sm">
                {Math.floor(countdown / 60)}:{(countdown % 60).toString().padStart(2, "0")}
              </span>
            </div>

            <div className="text-[11px] text-slate-400 space-y-1">
              <p>Please enter your Mobile Money PIN on your phone to complete payment.</p>
              <p className="text-slate-400">Never share your PIN with anyone.</p>
            </div>

            <div className="pt-2">
              <button
                type="button"
                onClick={handleResetPayment}
                className="text-xs text-slate-400 hover:text-white transition-colors"
              >
                ← Use a different number or network
              </button>
            </div>
          </div>
        ) : isOtpRequired ? (
          /* State 3: OTP Input Required */
          <div className="p-8 sm:p-10 rounded-2xl bg-slate-900 border border-slate-800 text-center space-y-6 shadow-2xl">
            <div className="w-14 h-14 rounded-full bg-amber-500/20 text-amber-400 text-2xl flex items-center justify-center mx-auto border border-amber-500/40">
              💬
            </div>
            <div>
              <h2 className="text-xl font-bold text-white">Enter OTP Code</h2>
              <p className="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                {paymentData?.display_text || "Please enter the one-time code sent to your mobile phone."}
              </p>
            </div>

            {otpError && (
              <div className="p-3 rounded-lg bg-red-950/60 border border-red-800 text-xs text-red-300">
                {otpError}
              </div>
            )}

            <form onSubmit={handleSubmitOtp} className="space-y-4">
              <input
                type="text"
                required
                maxLength={8}
                value={otp}
                onChange={(e) => setOtp(e.target.value)}
                placeholder="123456"
                className="w-full text-center text-2xl tracking-[0.5em] font-mono py-3 rounded-xl bg-slate-950 border border-slate-700 text-white focus:outline-none focus:border-emerald-500"
              />
              <button
                type="submit"
                disabled={submittingOtp || !otp.trim()}
                className="w-full py-3 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 text-slate-950 transition-colors shadow-lg shadow-emerald-500/20"
              >
                {submittingOtp ? "Verifying..." : "Authorize Payment →"}
              </button>
            </form>
          </div>
        ) : isFailed || isExpired ? (
          /* State 4: Failed or Timed Out */
          <div className="p-8 sm:p-10 rounded-2xl bg-slate-900 border border-red-900/40 text-center space-y-6 shadow-2xl">
            <div className="w-14 h-14 rounded-full bg-red-950/60 text-red-400 text-2xl flex items-center justify-center mx-auto border border-red-800">
              ✕
            </div>
            <div>
              <h2 className="text-xl font-bold text-white">
                {isExpired ? "Payment Window Expired" : "Payment Declined"}
              </h2>
              <p className="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                {isExpired
                  ? "The prompt was not approved in time. You can retry payment as long as the 15-minute ticket reservation holds."
                  : paymentStatus?.display_text || "The payment could not be processed by your mobile network."}
              </p>
            </div>

            <div className="pt-2 flex flex-col gap-2">
              <button
                type="button"
                onClick={handleResetPayment}
                className="w-full py-3 rounded-xl text-xs font-bold bg-emerald-500 hover:bg-emerald-600 text-slate-950 transition-colors"
              >
                Retry Mobile Money Payment 🔄
              </button>
              <Link
                href={`/events/${order.event?.slug}`}
                className="text-xs text-slate-400 hover:text-white py-2"
              >
                Back to Event
              </Link>
            </div>
          </div>
        ) : (
          /* State 5: Initial Payment Initiation Form */
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 space-y-6 shadow-2xl">
            <div>
              <span className="text-[11px] font-bold uppercase tracking-wider text-emerald-400">
                Payment Checkout
              </span>
              <h1 className="text-2xl font-bold text-white tracking-tight mt-1">
                Pay with Mobile Money
              </h1>
              <p className="text-xs text-slate-400 mt-1">
                Select your network to receive an approval prompt on your phone.
              </p>
            </div>

            {/* Total Amount Banner */}
            <div className="p-5 rounded-xl bg-slate-950 border border-slate-800 flex justify-between items-center">
              <div>
                <span className="text-[10px] uppercase font-semibold text-slate-400">
                  Total Amount Due
                </span>
                <div className="text-2xl font-black text-white">
                  {formatMoney(order.total_amount_minor)}
                </div>
              </div>
              <div className="text-right">
                <span className="text-[10px] text-emerald-400 font-semibold block">
                  Zero Customer Fees
                </span>
                <span className="text-xs text-slate-400">{order.items?.length || 1} tickets</span>
              </div>
            </div>

            {/* Network Selector Form */}
            <form onSubmit={handleInitiatePayment} className="space-y-5">
              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-2">
                  Select Mobile Money Network
                </label>
                <div className="grid grid-cols-3 gap-3">
                  {GHANA_MOBILE_MONEY_NETWORKS.map((network) => (
                    <button
                      key={network.code}
                      type="button"
                      onClick={() => setSelectedProvider(network.code)}
                      className={`p-3.5 rounded-xl border text-center transition-all flex flex-col items-center justify-center gap-1.5 ${
                        selectedProvider === network.code
                          ? "bg-emerald-950/30 border-emerald-500 shadow-md shadow-emerald-500/10"
                          : "bg-slate-950 border-slate-800 hover:border-slate-700"
                      }`}
                    >
                      <span className={`w-3 h-3 rounded-full ${network.badgeColor}`} />
                      <span className="text-xs font-bold text-white">{network.shortName}</span>
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1.5">
                  Mobile Money Phone Number
                </label>
                <input
                  type="tel"
                  required
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="024XXXXXXX or +23324XXXXXXX"
                  className="w-full px-4 py-2.5 rounded-lg bg-slate-950 border border-slate-700 text-sm text-white font-mono focus:outline-none focus:border-emerald-500"
                />
                <p className="text-[11px] text-slate-400 mt-1">
                  Ensure this phone is nearby to authorize the incoming USSD prompt.
                </p>
              </div>

              <button
                type="submit"
                disabled={initiating || !phone.trim()}
                className="w-full py-3.5 rounded-xl font-bold bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 text-slate-950 text-sm transition-colors shadow-lg shadow-emerald-500/20"
              >
                {initiating ? "Sending Prompt..." : `Send Prompt to ${phone || "Phone"} →`}
              </button>
            </form>

            <div className="pt-2 border-t border-slate-800/80 text-[10px] text-center text-slate-400">
              🔒 Powered by Paystack Ghana. 100% Secure Mobile Money Gateway.
            </div>
          </div>
        )}
      </main>

      {/* Footer */}
      <footer className="border-t border-slate-900 bg-slate-950 py-6 text-center text-xs text-slate-400">
        <p>© 2026 ClevaWave Tickets. Built for Ghana Mobile Money.</p>
      </footer>
    </div>
  );
}

export default function PaymentPage({ params }: PaymentPageProps) {
  return (
    <Suspense
      fallback={
        <div className="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-8">
          <div className="text-center space-y-2">
            <div className="text-2xl animate-spin">⏳</div>
            <p className="text-xs text-slate-400">Loading payment page...</p>
          </div>
        </div>
      }
    >
      <PaymentContent params={params} />
    </Suspense>
  );
}

