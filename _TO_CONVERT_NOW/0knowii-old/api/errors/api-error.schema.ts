import { z } from 'zod';

export const apiErrorSchema = z.object({
  detail: z.string(),
  detailKey: z.string(),
  detailKeyParameters: z.string().optional(),
  fields: z.string().array().optional(),
  status: z.number().optional(),
  index: z.number().optional(),
  metadata: z.any().optional(),
});

export type ApiError = z.infer<typeof apiErrorSchema>;
