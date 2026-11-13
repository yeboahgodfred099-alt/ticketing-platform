export interface ProviderBreakdown {
  provider: string;
  count: number;
  total_pesewas: number;
}

export interface TierMetric {
  id: string;
  name: string;
  price_pesewas: number;
  sold: number;
  reserved: number;
  total: number;
  revenue_pesewas: number;
}

export interface ReportOverview {
  revenue: {
    gross_pesewas: number;
    absorbed_fees_pesewas: number;
    net_pesewas: number;
  };
  orders: {
    total_orders: number;
    total_paid_orders: number;
    pending_orders: number;
    conversion_rate: number;
  };
  tickets: {
    total_sold: number;
    total_reserved: number;
  };
  attendance: {
    checked_in: number;
    attendance_rate: number;
  };
  providers: ProviderBreakdown[];
  tiers: TierMetric[];
}

