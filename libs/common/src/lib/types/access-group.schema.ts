import { z } from 'zod';
import { MAX_LENGTH_ACCESS_GROUP_DESCRIPTION, MAX_LENGTH_ACCESS_GROUP_NAME, MIN_LENGTH_ACCESS_GROUP_NAME } from '../constants';

/**
 * An access group (#1241, ADR 0025): a named group of members that opens the spaces with the "groups" visibility.
 * WARNING: aligned with AccessGroupResource.php
 */
export const accessGroupSchema = z.object({
  cuid: z.string(),
  name: z.string(),
  description: z.string(),
  members_count: z.number().int().optional(),
  spaces_count: z.number().int().optional(),
  created_at: z.string(),
});
export type AccessGroup = z.infer<typeof accessGroupSchema>;

// WARNING: aligned with AccessGroupInputRules.php
export const accessGroupFormSchema = z.object({
  name: z
    .string()
    .trim()
    .min(MIN_LENGTH_ACCESS_GROUP_NAME, {
      error: `Use between ${MIN_LENGTH_ACCESS_GROUP_NAME} and ${MAX_LENGTH_ACCESS_GROUP_NAME} characters`,
    })
    .max(MAX_LENGTH_ACCESS_GROUP_NAME, { error: `The name is too long (maximum ${MAX_LENGTH_ACCESS_GROUP_NAME} characters)` }),
  description: z.string().max(MAX_LENGTH_ACCESS_GROUP_DESCRIPTION, {
    error: `The description is too long (maximum ${MAX_LENGTH_ACCESS_GROUP_DESCRIPTION} characters)`,
  }),
});
export type AccessGroupForm = z.infer<typeof accessGroupFormSchema>;

/**
 * Who to add to a group: exactly one of the user cuid, the username or the email address
 */
export type AccessGroupMemberInput = { member: string } | { username: string } | { email: string };
