import { z } from 'zod';

export const PricingPlanIntervalSchema = z.enum(['day', 'week', 'month', 'year']);

export type PricingPlanIntervalType = `${z.infer<typeof PricingPlanIntervalSchema>}`;

export default PricingPlanIntervalSchema;
