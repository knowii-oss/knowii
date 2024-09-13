import { z } from 'zod';

import { apiErrorsSchema } from './errors/api-errors.schema';

/**
 * Base schema for single item API responses
 * @param itemSchema
 */
export function singleItemApiResponseSchema<ItemType extends z.ZodTypeAny>(itemSchema: ItemType) {
  return z.object({
    message: itemSchema.optional(),
    data: itemSchema.optional(),
    errors: apiErrorsSchema.optional(),
  });
}
