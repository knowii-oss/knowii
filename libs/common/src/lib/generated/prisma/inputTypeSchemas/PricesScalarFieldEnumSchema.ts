import { z } from 'zod';

export const PricesScalarFieldEnumSchema = z.enum([
  'id',
  'product_id',
  'active',
  'description',
  'unit_amount',
  'currency',
  'type',
  'interval',
  'interval_count',
  'trial_period_days',
  'metadata',
]);

export default PricesScalarFieldEnumSchema;
