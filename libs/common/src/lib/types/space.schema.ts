import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';
import {
  MAX_LENGTH_SPACE_DESCRIPTION,
  MAX_LENGTH_SPACE_EMOJI,
  MAX_LENGTH_SPACE_GROUP_NAME,
  MAX_LENGTH_SPACE_NAME,
  MIN_LENGTH_SPACE_GROUP_NAME,
  MIN_LENGTH_SPACE_NAME,
} from '../constants';

/**
 * Space groups and spaces (#1173, ADR 0012).
 * WARNING: the enums are aligned with KnowiiSpaceType, KnowiiSpaceVisibility and KnowiiSpacePostingPolicy (back-end)
 */
export const SPACE_TYPES = ['discussion', 'chat', 'pages', 'course', 'resources'] as const;
export const spaceTypeSchema = z.enum(SPACE_TYPES);
export type SpaceType = z.infer<typeof spaceTypeSchema>;

export const SPACE_VISIBILITIES = ['public', 'members', 'groups'] as const;
export const spaceVisibilitySchema = z.enum(SPACE_VISIBILITIES);
export type SpaceVisibility = z.infer<typeof spaceVisibilitySchema>;

export const SPACE_POSTING_POLICIES = ['members', 'moderators'] as const;
export const spacePostingPolicySchema = z.enum(SPACE_POSTING_POLICIES);
export type SpacePostingPolicy = z.infer<typeof spacePostingPolicySchema>;

export const SPACE_TYPE_LABELS: Record<SpaceType, { name: string; description: string; emoji: string }> = {
  discussion: { name: 'Discussion', description: 'Posts with comments and reactions', emoji: '💬' },
  chat: { name: 'Chat', description: 'Real-time messages with threads', emoji: '⚡' },
  pages: { name: 'Pages', description: 'Ordered pages: guides, Start Here', emoji: '📄' },
  course: { name: 'Course', description: 'Ordered lessons with videos', emoji: '🎓' },
  resources: { name: 'Resources', description: 'Curated links to articles', emoji: '🔗' },
};

export const SPACE_VISIBILITY_LABELS: Record<SpaceVisibility, string> = {
  public: 'Public: anyone can read',
  members: 'Members only',
  // #1241, ADR 0025: the members of the chosen access groups, the owner and the admins
  groups: 'Access groups only',
};

export const SPACE_POSTING_POLICY_LABELS: Record<SpacePostingPolicy, string> = {
  members: 'All members can post',
  moderators: 'Only moderators can post',
};

// Letters, digits and markup are not an emoji. WARNING: aligned with SpaceInputRules.php
const NOT_AN_EMOJI_REGEX = /[\p{L}\p{N}<>]/u;

export const spaceGroupFormSchema = z.object({
  name: z
    .string()
    .trim()
    .min(MIN_LENGTH_SPACE_GROUP_NAME, { error: `Use between ${MIN_LENGTH_SPACE_GROUP_NAME} and ${MAX_LENGTH_SPACE_GROUP_NAME} characters` })
    .max(MAX_LENGTH_SPACE_GROUP_NAME, { error: `The name is too long (maximum ${MAX_LENGTH_SPACE_GROUP_NAME} characters)` }),
});
export type SpaceGroupForm = z.infer<typeof spaceGroupFormSchema>;

export const spaceFormSchema = z.object({
  // WARNING: those rules must remain aligned with SpaceInputRules.php
  name: z
    .string()
    .trim()
    .min(MIN_LENGTH_SPACE_NAME, { error: `Use between ${MIN_LENGTH_SPACE_NAME} and ${MAX_LENGTH_SPACE_NAME} characters` })
    .max(MAX_LENGTH_SPACE_NAME, { error: `The name is too long (maximum ${MAX_LENGTH_SPACE_NAME} characters)` }),
  description: z.string().max(MAX_LENGTH_SPACE_DESCRIPTION, {
    error: `The description is too long (maximum ${MAX_LENGTH_SPACE_DESCRIPTION} characters)`,
  }),
  emoji: z
    .string()
    .max(MAX_LENGTH_SPACE_EMOJI, { error: 'Use one emoji' })
    .refine((value) => !NOT_AN_EMOJI_REGEX.test(value), { error: 'Use one emoji, not text' }),
  type: spaceTypeSchema,
  visibility: spaceVisibilitySchema,
  posting_policy: spacePostingPolicySchema,
  space_group_cuid: z.string().min(1, { error: 'Choose a space group' }),
  // Cuids of the access groups of a "groups" space (#1241)
  access_groups: z.array(z.string()),
});
export type SpaceForm = z.infer<typeof spaceFormSchema>;

// A "groups" space needs at least one group (#1241). WARNING: aligned with SpaceAccessGroups.php
export const spaceFormWithGroupsSchema = spaceFormSchema.refine((form) => form.visibility !== 'groups' || form.access_groups.length > 0, {
  error: 'Choose at least one access group',
  path: ['access_groups'],
});

export const spaceSchema = baseEntitySchema.extend({
  name: z.string(),
  slug: z.string(),
  description: z.string(),
  emoji: z.string(),
  type: spaceTypeSchema,
  visibility: spaceVisibilitySchema,
  posting_policy: spacePostingPolicySchema,
  position: z.number().int(),
  space_group_cuid: z.string(),
  // The groups that open a "groups" space (#1241)
  access_groups: z.array(z.object({ cuid: z.string(), name: z.string() })).optional(),
});
export type Space = z.infer<typeof spaceSchema>;

export const spaceGroupSchema = baseEntitySchema.extend({
  name: z.string(),
  position: z.number().int(),
  // Only the spaces that the current user can see
  spaces: z.array(spaceSchema).optional(),
});
export type SpaceGroup = z.infer<typeof spaceGroupSchema>;

export interface SpacePermissions {
  // WARNING: aligned with SpaceController.php
  canManageSpaces: boolean;
  canContribute: boolean;
  canModerate: boolean;
  canReact: boolean;
}

/**
 * Move an item one step up or down in a list, for keyboard-friendly reordering. Returns a new list.
 */
export function moveItem<T>(items: readonly T[], index: number, direction: -1 | 1): T[] {
  const target = index + direction;
  const copy = [...items];
  if (index < 0 || index >= items.length || target < 0 || target >= items.length) {
    return copy;
  }
  const [item] = copy.splice(index, 1);
  if (item !== undefined) {
    copy.splice(target, 0, item);
  }
  return copy;
}
