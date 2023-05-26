import { z } from 'zod';

export const SubscriptionsScalarFieldEnumSchema = z.enum([
  'id',
  'user_id',
  'user_id_external',
  'status',
  'metadata',
  'product_id',
  'price_id',
  'quantity',
  'cancel_at_period_end',
  'created_at',
  'current_period_start',
  'current_period_end',
  'ended_at',
  'cancel_at',
  'canceled_at',
  'trial_start',
  'trial_end',
]);

export default SubscriptionsScalarFieldEnumSchema;
