import { z } from 'zod';
import { authorSchema } from './post.schema';

/**
 * The answer of a member to an event (#1515, ADR 0053): "Yes", "Replay only" (only when the host says that a replay
 * will exist) or "No". No answer is null, never a fourth value.
 * WARNING: aligned with KnowiiEventAnswer.php
 */
export const EVENT_ANSWERS = ['yes', 'replay', 'no'] as const;
export const eventAnswerValueSchema = z.enum(EVENT_ANSWERS);
export type EventAnswerValue = z.infer<typeof eventAnswerValueSchema>;

/**
 * The answer of the reader to an event: `answer` and `updated_at` are null when the member did not answer. It names no
 * member: only the member who gave it gets it.
 * WARNING: aligned with EventAnswerResource.php
 */
export const eventAnswerSchema = z.object({
  answer: eventAnswerValueSchema.nullable(),
  updated_at: z.coerce.date().nullable(),
});
export type EventAnswer = z.infer<typeof eventAnswerSchema>;

/**
 * The numbers of the answers of an event, for its host, the owner and the admins only. Only the people who are members
 * today and can read the space are counted.
 * WARNING: aligned with EventAnswerApiController::index (`metadata.counts`)
 */
export const eventAnswerCountsSchema = z.object({
  yes: z.number().int().nonnegative(),
  replay: z.number().int().nonnegative(),
  no: z.number().int().nonnegative(),
});
export type EventAnswerCounts = z.infer<typeof eventAnswerCountsSchema>;

/**
 * A member who answered Yes or Replay only, as the list of the host gives them: the public summary of a member. A member
 * who answered No is never named.
 */
export const eventAnswerMemberSchema = authorSchema;
export type EventAnswerMember = z.infer<typeof eventAnswerMemberSchema>;
