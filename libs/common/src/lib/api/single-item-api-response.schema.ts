import { z } from 'zod';

import { baseApiResponseSchema } from './base-api-response.schema';

/**
 * Base schema for single item API responses
 * @param dataSchema
 * @param metadataSchema
 */
export function singleItemApiResponseSchema<DataType extends z.ZodTypeAny, MetadataType extends z.ZodTypeAny>(
  dataSchema: DataType,
  metadataSchema: MetadataType,
) {
  return baseApiResponseSchema(dataSchema, metadataSchema);
}
