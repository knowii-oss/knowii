import { z } from 'zod';

// WARNING: aligned with KnowiiCaptureSource.php
export const captureSourceSchema = z.enum(['server', 'extension']);

export type CaptureSource = z.infer<typeof captureSourceSchema>;

// WARNING: aligned with KnowiiCaptureStatus.php
export const captureStatusSchema = z.enum(['pending', 'capturing', 'captured', 'failed']);

export type CaptureStatus = z.infer<typeof captureStatusSchema>;

/**
 * The captured content of a community resource (ADR 0018), for the members of its community only. The Markdown comes
 * with the single resource only.
 */
export const resourceCaptureSchema = z.object({
  markdown: z.string().nullable(),
  word_count: z.number().int(),
  reading_time: z.number().int(),
  source: captureSourceSchema,
  captured_at: z.coerce.date(),
});

export type ResourceCapture = z.infer<typeof resourceCaptureSchema>;
