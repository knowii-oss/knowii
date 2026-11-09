import { z } from 'zod';
import { MAX_LENGTH_UPLOAD_ALT } from '../constants';

/**
 * WARNING: aligned with UploadResource.php
 */
export const uploadSchema = z.object({
  cuid: z.string(),
  url: z.string(),
  mime_type: z.string(),
  width: z.number().int(),
  height: z.number().int(),
  size_bytes: z.number().int(),
  alt: z.string(),
  created_at: z.coerce.date(),
});
export type Upload = z.infer<typeof uploadSchema>;

/**
 * WARNING: aligned with UploadImage.php
 */
export const uploadAltSchema = z
  .string()
  .trim()
  .min(1, { error: 'Describe the image for people who cannot see it' })
  .max(MAX_LENGTH_UPLOAD_ALT, { error: `The description is too long (maximum ${MAX_LENGTH_UPLOAD_ALT} characters)` });
