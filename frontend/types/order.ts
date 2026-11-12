export interface OrderItemData {
  ticket_type_id: string;
  ticket_name: string;
  quantity: number;
  unit_price_minor: number;
  unit_price_major: string;
  total_minor: number;
  total_major: string;
  currency: string;
}

export interface OrderData {
  order_number: string;
  status: "pending_payment" | "paid" | "expired" | "cancelled" | "partially_refunded" | "refunded";
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  subtotal_minor: number;
  subtotal_major: string;
  discount_minor: number;
  discount_major: string;
  total_amount_minor: number;
  total_amount_major: string;
  currency: string;
  expires_at: string | null;
  paid_at: string | null;
  created_at: string;
  access_token?: string;
  items?: OrderItemData[];
  event?: {
    id: string;
    name: string;
    slug: string;
    venue_name: string | null;
    starts_at: string;
    ends_at: string;
    timezone: string;
  };
}

export interface PaymentInitiateResponse {
  status: "pending" | "requires_action" | "success" | "failed";
  reference: string;
  order_number: string;
  display_text: string;
  timeout_seconds?: number;
  action?: "send_otp";
}

export interface PaymentStatusResponse {
  found: boolean;
  reference: string;
  order_number: string;
  status: "pending" | "requires_action" | "success" | "failed" | "expired" | "abandoned";
  amount_minor: number;
  amount_major: string;
  currency: string;
  provider: string;
  display_text: string;
  is_final: boolean;
  expires_at: string | null;
}


