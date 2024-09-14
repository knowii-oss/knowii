import { z } from 'zod';

import { apiErrorsSchema } from './errors/api-errors.schema';

/**
 * Base schema for single item API responses
 * @param itemSchema
 * @param metadataSchema
 */
export function singleItemApiResponseSchema<ItemType extends z.ZodTypeAny, MetadataType extends z.ZodTypeAny>(itemSchema: ItemType, metadataSchema: MetadataType) {
  return z.object({
    data: itemSchema.optional(),
    errors: apiErrorsSchema.optional(),
    message: z.string().optional(),
    metadata: metadataSchema.optional(),
  });
}

export const emptyMetadataSchema = z.object({});
