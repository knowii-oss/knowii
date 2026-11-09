import { z } from 'zod';

/**
 * What a member shows to the other members of a community. WARNING: aligned with MemberProfileResource.php
 */
export const memberProfileSchema = z.object({
  cuid: z.string(),
  name: z.string(),
  username: z.string(),
  bio: z.string(),
  location: z.string(),
  social_links: z.record(z.string(), z.string()),
  member_since: z.coerce.date(),
});
export type MemberProfile = z.infer<typeof memberProfileSchema>;
