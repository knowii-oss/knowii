import { z } from 'zod';

export const PricingTypeSchema = z.enum(['one_time', 'recurring']);

export type PricingTypeType = `${z.infer<typeof PricingTypeSchema>}`;

export default PricingTypeSchema;
