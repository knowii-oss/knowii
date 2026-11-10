import { z } from 'zod';

/**
 * A member in a presence channel or an online list (#1187, ADR 0027): the public summary, identified by the cuid.
 * WARNING: aligned with Presence::info (Presence.php)
 */
export const presenceMemberSchema = z.object({
  cuid: z.string(),
  name: z.string(),
  username: z.string(),
  avatar_url: z.string().nullable(),
});
export type PresenceMember = z.infer<typeof presenceMemberSchema>;

/**
 * "Alice is typing", "Alice and Bob are typing", "Several people are typing" (#1187). Null when nobody types.
 */
export function typingText(names: readonly string[]): string | null {
  if (names.length === 0) {
    return null;
  }
  if (names.length === 1) {
    return `${names[0]} is typing…`;
  }
  if (names.length === 2) {
    return `${names[0]} and ${names[1]} are typing…`;
  }
  return 'Several people are typing…';
}
