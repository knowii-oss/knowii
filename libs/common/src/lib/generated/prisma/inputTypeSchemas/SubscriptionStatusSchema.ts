import { z } from 'zod';

export const SubscriptionStatusSchema = z.enum([
  'trialing',
  'active',
  'canceled',
  'incomplete',
  'incomplete_expired',
  'past_due',
  'paused',
  'unpaid',
]);

export type SubscriptionStatusType = `${z.infer<typeof SubscriptionStatusSchema>}`;

export default SubscriptionStatusSchema;
