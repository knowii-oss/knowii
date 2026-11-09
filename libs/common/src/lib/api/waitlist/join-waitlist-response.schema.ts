import { z } from 'zod';
import { singleItemApiResponseSchema } from '../single-item-api-response.schema';

export const joinWaitlistResponseSchema = singleItemApiResponseSchema(z.unknown(), z.unknown());

export type JoinWaitlistResponse = z.infer<typeof joinWaitlistResponseSchema>;
