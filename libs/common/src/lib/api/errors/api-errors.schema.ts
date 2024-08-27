import { z } from 'zod';

import { apiErrorSchema } from './api-error.schema';
import { ErrorType } from './error-type.intf';
import { ErrorCategory } from './error-category.intf';

/**
 * API errors schema, based on RFC5646
 * References:
 * https://tools.ietf.org/html/rfc5646
 * https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki/Error-handling-Error-details
 */
export const apiErrorsSchema = z.object({
  errorDetails: z.array(apiErrorSchema),
  type: z.nativeEnum(ErrorType),
  category: z.nativeEnum(ErrorCategory),
  title: z.string(),
  titleKey: z.string(),
  titleKeyParameters: z.string().optional(),
  instance: z.string().uuid().optional(),
  timestamp: z.string().datetime().optional(),
  metadata: z.any().optional(),
});

export type ApiErrors = z.infer<typeof apiErrorsSchema>;
