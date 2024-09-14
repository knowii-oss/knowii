import { z } from 'zod';
import { knowiiApiResponseCategorySchema } from './knowii-api-response-category.schema';
import { knowiiApiResponseTypeSchema } from './knowii-api-response-type.schema';

/**
 * Base schema for API responses
 * @param dataSchema
 * @param metadataSchema
 */
export function baseApiResponseSchema<DataType extends z.ZodTypeAny, MetadataType extends z.ZodTypeAny>(
  dataSchema: DataType,
  metadataSchema: MetadataType,
) {
  return z.object({
    category: knowiiApiResponseCategorySchema,
    type: knowiiApiResponseTypeSchema,
    message: z.string(),
    data: dataSchema.optional(),
    errors: z.any().optional(),
    metadata: metadataSchema.optional(),
  });
}
