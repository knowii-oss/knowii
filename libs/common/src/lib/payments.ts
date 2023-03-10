export interface SubscriptionPlan {
  id: string;
  name: string;
  description: string;
  features: string[];
  prices: PriceDetails[];
}

export interface PriceDetails {
  id: string;
  unit_amount: number | null;
  currency: string;
  interval: string | null;
  interval_count: number | null;
}
