import { z } from 'zod';
import { MAX_LENGTH_WAITLIST_EMAIL } from '../../constants';

// WARNING: Those rules must remain aligned with those in JoinWaitlist.php
export const waitlistEmailSchema = z
  .email({ error: 'Please enter a valid email address.' })
  .max(MAX_LENGTH_WAITLIST_EMAIL, { error: `The email address can have at most ${MAX_LENGTH_WAITLIST_EMAIL} characters.` });

export const joinWaitlistRequestSchema = z.object({
  email: waitlistEmailSchema,
  /**
   * Cloudflare Turnstile token (bot check)
   */
  turnstileToken: z.string().min(1),
  /**
   * Honeypot: must stay empty (WAITLIST_HONEYPOT_FIELD)
   */
  website: z.string().optional(),
});

export type JoinWaitlistRequest = z.infer<typeof joinWaitlistRequestSchema>;
