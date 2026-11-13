export interface RetrievedTicket {
  id: string;
  ticket_number: string;
  category_name: string;
  holder_name: string;
  status: "active" | "checked_in" | "cancelled" | "refunded";
  issued_at: string;
  used_at: string | null;
  qr_code_data_uri: string;
  raw_qr_token: string;
}

export interface RetrievedTicketsData {
  order: {
    order_number: string;
    customer_name: string;
    customer_email: string;
    total_amount_minor: number;
    currency: string;
    paid_at: string | null;
  };
  event: {
    id: string;
    name: string;
    slug: string;
    banner_url: string | null;
    venue_name: string | null;
    venue_address: string | null;
    starts_at: string;
    ends_at: string;
    timezone: string;
  };
  tickets: RetrievedTicket[];
}
