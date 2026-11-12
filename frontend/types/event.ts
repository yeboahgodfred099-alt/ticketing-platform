export interface TicketType {
  id: string;
  event_id: string;
  name: string;
  description: string | null;
  price_minor: number;
  price_major: string;
  currency: string;
  quantity_total: number;
  quantity_sold: number;
  quantity_reserved: number;
  available_quantity: number;
  min_per_order: number;
  max_per_order: number | null;
  sales_starts_at: string | null;
  sales_ends_at: string | null;
  is_within_sale_window: boolean;
  is_sold_out: boolean;
  status: "active" | "inactive" | "archived";
}

export interface EventItem {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  banner_url: string | null;
  venue_name: string | null;
  venue_address: string | null;
  starts_at: string;
  ends_at: string;
  sales_starts_at: string | null;
  sales_ends_at: string | null;
  timezone: string;
  currency: string;
  lowest_price_minor?: number;
  lowest_price_formatted?: string;
  status: "draft" | "published" | "unpublished" | "cancelled" | "archived";
  is_purchasable: boolean;
  is_sold_out?: boolean;
  ticket_types?: TicketType[];
  total_capacity?: number;
  total_sold?: number;
  created_at?: string;
}

