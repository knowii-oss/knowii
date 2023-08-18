import { z } from 'zod';
import { apiErrorsSchema } from './api-errors.schema';

// TODO extend to support collection responses
/**
 * Base schema for API responses
 * @param itemSchema
 */
export function createApiResponseSchema<ItemType extends z.ZodTypeAny>(itemSchema: ItemType) {
  return z.object({
    data: itemSchema.optional(),
    errors: apiErrorsSchema.optional(),
  });
}
